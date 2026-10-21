<?php

use App\Enums\StockMovementType as T;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Banner;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Customer;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingCompany;
use App\Models\Tenant;
use App\Models\TenantProduct;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    codUpfront($this->tenant, false);   // legacy tests: carrier collects the full total
    $this->s = 'http://egy.3alraf.test/ar';
    app()->instance('currentTenant', $this->tenant);
    $this->branch = Branch::first();
    $this->city = City::first();
    $this->company = ShippingCompany::first();
    $this->cod = PaymentMethod::where('type', 'cod')->first();
    $this->cat = Category::create(['name' => ['ar' => 'أجهزة', 'en' => 'Devices'], 'slug' => 'devices', 'path' => 'x', 'depth' => 0, 'is_active' => true]);
    $this->cat->update(['path' => (string) $this->cat->id]);
    $this->sub = Category::create(['parent_id' => $this->cat->id, 'name' => ['ar' => 'هواتف', 'en' => 'Phones'], 'slug' => 'phones', 'path' => $this->cat->id.'/x', 'depth' => 1, 'is_active' => true]);
    $this->sub->update(['path' => $this->cat->id.'/'.$this->sub->id]);
    $this->brand = Brand::create(['name' => ['ar' => 'سوني', 'en' => 'Sony'], 'slug' => 'sony', 'is_active' => true]);
    $this->mk = function (string $slug, $price, ?Category $cat = null, ?Brand $brand = null, $sale = null) {
        $p = Product::create(['type' => 'simple', 'name' => ['ar' => "منتج $slug", 'en' => "P $slug"], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique(), 'brand_id' => $brand?->id]);
        if ($cat) {
            $p->categories()->attach($cat->id);
        }
        TenantProduct::create(['product_id' => $p->id, 'price' => $price, 'sale_price' => $sale, 'cost' => 10, 'is_active' => true]);
        app(StockLedger::class)->record($this->branch->id, $p->id, 0, T::PURCHASE_IN, 50, '10.00');

        return $p;
    };
    $this->p1 = ($this->mk)('cheap', 100, $this->sub, $this->brand);
    $this->p2 = ($this->mk)('mid', 300, $this->cat);
    $this->p3 = ($this->mk)('sale', 500, $this->sub, null, 400);
});

test('home renders menu, rails, flash sale countdown and banners', function () {
    $fs = FlashSale::create(['name' => ['ar' => 'عرض', 'en' => 'Flash'], 'starts_at' => now()->subHour(), 'ends_at' => now()->addDay(), 'is_active' => true]);
    FlashSaleItem::create(['flash_sale_id' => $fs->id, 'product_id' => $this->p1->id, 'product_variant_id' => 0, 'discount_type' => 'percent', 'value' => 20]);
    Banner::create(['placement' => 'home_hero', 'title' => ['ar' => 'بانر', 'en' => 'Hero'], 'images' => ['ar' => 'banners/x.webp'], 'link_url' => '/ar', 'sort_order' => 1, 'is_active' => true]);

    $this->get("{$this->s}")->assertOk()->assertSee('dir="rtl"', false)->assertSee('أجهزة')->assertSee('data-slider', false)->assertSee('data-countdown', false)
        ->assertSee('منتج cheap')->assertSee('80.00')->assertSee('data-cart-count', false)->assertSee('hreflang="en"', false)->assertSee('application/ld+json', false);
    $this->get('http://egy.3alraf.test/en')->assertOk()->assertSee('dir="ltr"', false)->assertSee('Devices');
});

test('category listing filters by subtree, brand, price, sort and serves an AJAX fragment', function () {
    $this->get("{$this->s}/category/devices")->assertOk()->assertSee('منتج cheap')->assertSee('منتج mid')->assertSee('منتج sale')->assertSee('هواتف');
    $this->get("{$this->s}/category/phones")->assertOk()->assertSee('منتج cheap')->assertDontSee('منتج mid');
    $this->get("{$this->s}/category/devices?brands[]={$this->brand->id}")->assertOk()->assertSee('منتج cheap')->assertDontSee('منتج mid');
    $this->get("{$this->s}/category/devices?price_min=250&price_max=450")->assertOk()->assertSee('منتج mid')->assertSee('منتج sale')->assertDontSee('منتج cheap');

    $json = $this->getJson("{$this->s}/category/devices?sort=price_desc")->assertOk()->json();
    expect($json['count'])->toBe(3)->and($json['html'])->toContain('data-product-card');
    expect(strpos($json['html'], 'منتج mid'))->toBeLessThan(strpos($json['html'], 'منتج cheap')); // 300 before 100
    $this->get("{$this->s}/category/nope")->assertNotFound();
});

test('attribute filter works with variants', function () {
    $attr = Attribute::create(['name' => ['ar' => 'اللون', 'en' => 'Color'], 'code' => 'color', 'type' => 'color']);
    $red = AttributeValue::create(['attribute_id' => $attr->id, 'value' => 'Red', 'color_hex' => '#f00']);
    $v = ProductVariant::create(['product_id' => $this->p2->id, 'combination_hash' => (string) $red->id, 'sku' => 'MID-R', 'barcode' => '2000000000011', 'is_active' => true]);
    $v->values()->attach($red->id);
    $this->get("{$this->s}/category/devices")->assertOk()->assertSee('اللون')->assertSee('Red');
    $this->get("{$this->s}/category/devices?values[]={$red->id}")->assertOk()->assertSee('منتج mid')->assertDontSee('منتج cheap');
});

test('search results and suggestions', function () {
    $this->get("{$this->s}/search?q=mid")->assertOk()->assertSee('منتج mid')->assertDontSee('منتج cheap');
    $this->get("{$this->s}/search")->assertOk();
    $this->getJson("{$this->s}/search/suggest?q=cheap")->assertOk()->assertJsonPath('products.0.name', 'منتج cheap')->assertJsonPath('products.0.price', '100.00');
    $this->getJson("{$this->s}/search/suggest?q=ه")->assertOk()->assertJsonPath('products', []);
    $this->getJson("{$this->s}/search/suggest?q=هوات")->assertOk()->assertJsonPath('categories.0.name', 'هواتف');
});

test('product page exposes the variant picker with stock, barcode and flash price, plus SEO', function () {
    $attr = Attribute::create(['name' => ['ar' => 'المقاس', 'en' => 'Size'], 'code' => 'size', 'type' => 'text']);
    $m = AttributeValue::create(['attribute_id' => $attr->id, 'value' => 'M']);
    $l = AttributeValue::create(['attribute_id' => $attr->id, 'value' => 'L']);
    $p = Product::create(['type' => 'variable', 'name' => ['ar' => 'قميص', 'en' => 'Shirt'], 'slug' => 'shirt', 'sku' => 'SH', 'meta_description' => ['ar' => 'وصف ميتا', 'en' => 'meta']]);
    TenantProduct::create(['product_id' => $p->id, 'price' => 200, 'cost' => 50, 'is_active' => true]);
    foreach ([$m, $l] as $i => $val) {
        $v = ProductVariant::create(['product_id' => $p->id, 'combination_hash' => (string) $val->id, 'sku' => "SH-$i", 'barcode' => '200000000010'.$i, 'is_active' => true]);
        $v->values()->attach($val->id);
        if ($i === 0) {
            app(StockLedger::class)->record($this->branch->id, $p->id, $v->id, T::PURCHASE_IN, 3, '50.00');
        }
    }
    $res = $this->get("{$this->s}/product/shirt")->assertOk()->assertSee('data-option', false)->assertSee('"@type":"Product"', false)->assertSee('og:title', false)->assertSee('وصف ميتا', false);
    $picker = $res->viewData('picker');
    expect($picker['variable'])->toBeTrue()->and($picker['options'][0]['values'])->toHaveCount(2)->and($picker['variants'])->toHaveCount(2);
    expect($picker['variants'][0]['stock'])->toBe(3)->and($picker['variants'][0]['in_stock'])->toBeTrue()->and($picker['variants'][1]['in_stock'])->toBeFalse()
        ->and($picker['variants'][0]['barcode'])->toBe('2000000000100');

    $fs = FlashSale::create(['name' => ['ar' => 'ع', 'en' => 'F'], 'starts_at' => now()->subHour(), 'ends_at' => now()->addDay(), 'is_active' => true]);
    FlashSaleItem::create(['flash_sale_id' => $fs->id, 'product_id' => $p->id, 'product_variant_id' => 0, 'discount_type' => 'percent', 'value' => 10]);
    $picker = $this->get("{$this->s}/product/shirt")->viewData('picker');
    expect($picker['variants'][0]['price'])->toBe('180.00')->and($picker['variants'][0]['old'])->toBe('200.00')->and($picker['variants'][0]['flash_ends'])->not->toBeNull();
});

test('cart page, live quote with partial selections, shipping options and checkout flow', function () {
    $this->get("{$this->s}/cart")->assertOk()->assertSee('data-cart-page', false);
    $this->withCredentials()->postJson("{$this->s}/cart/items", ['product_id' => $this->p1->id, 'variant_id' => 0, 'quantity' => 2])->assertOk();
    $this->withCredentials()->getJson("{$this->s}/cart")->assertOk()->assertJsonPath('data.items.0.slug', 'cheap')->assertJsonPath('data.totals.subtotal', '200.00');
    // quote without shipping/payment/customer fields must not 422
    $this->withCredentials()->postJson("{$this->s}/cart/quote", [])->assertOk()->assertJsonPath('data.totals.subtotal', '200.00');
    $this->withCredentials()->postJson("{$this->s}/cart/quote", ['discount_code' => 'NOPE'])->assertStatus(422)->assertJsonValidationErrors('discount_code');

    $this->withCredentials()->get("{$this->s}/checkout")->assertOk()->assertSee('data-checkout', false)->assertSee($this->cod->name);
    $opts = $this->withCredentials()->getJson("{$this->s}/checkout/shipping?city_id={$this->city->id}")->assertOk()->json('data.options');
    expect($opts)->not->toBeEmpty()->and($opts[0])->toHaveKeys(['id', 'name', 'fee', 'total', 'supports_cod']);
    expect(bccomp($opts[0]['total'], '200.00', 2))->toBeGreaterThanOrEqual(0);

    $payload = ['name' => 'Ali', 'phone' => '0100', 'city_id' => $this->city->id, 'address' => 'St 1', 'shipping_company_id' => $this->company->id, 'payment_method_id' => $this->cod->id];
    $res = $this->withCredentials()->postJson("{$this->s}/checkout", $payload)->assertOk();
    $token = $res->json('data.token');
    $this->get("{$this->s}/order/$token")->assertOk()->assertSee($res->json('data.number'))->assertSee('منتج cheap');
    $this->get("{$this->s}/order/$token?invoice=1")->assertOk();
    $this->withCredentials()->get("{$this->s}/checkout")->assertRedirect("{$this->s}/cart"); // cart emptied
});

test('account area for a logged in customer', function () {
    $this->postJson("{$this->s}/register", ['name' => 'Sara', 'email' => 'sara@egy.test', 'password' => 'secret123', 'password_confirmation' => 'secret123'])->assertOk();
    $c = Customer::where('email', 'sara@egy.test')->first();
    expect($c)->not->toBeNull();
    $this->actingAs($c, 'web')->get("{$this->s}/account")->assertOk()->assertSee('sara@egy.test');
    $this->actingAs($c, 'web')->putJson("{$this->s}/account/profile", ['name' => 'Sara B', 'email' => 'sara@egy.test'])->assertOk();
    expect($c->fresh()->name)->toBe('Sara B');
    $this->postJson("{$this->s}/register", ['name' => 'X', 'email' => 'sara@egy.test', 'password' => 'secret123', 'password_confirmation' => 'secret123'])->assertStatus(302); // already authenticated
    $this->get("{$this->s}/account")->assertOk();
    auth('web')->logout();
    $this->get("{$this->s}/account")->assertRedirect();
});

test('sitemap, custom/static pages keep working inside the new layout', function () {
    $this->get('http://egy.3alraf.test/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->assertSee('/ar/product/cheap', false)->assertSee('/en/category/devices', false);
    $this->get("{$this->s}/page/about")->assertOk()->assertSee('data-cart-count', false);
    $this->get('http://egy.3alraf.test/offers')->assertOk()->assertSee('data-cart-count', false)->assertSee('?lang=en', false);
});
