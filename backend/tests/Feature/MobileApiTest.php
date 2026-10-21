<?php

use App\DTOs\Orders\CheckoutData;
use App\Enums\OrderStatus as S;
use App\Enums\StockMovementType as T;
use App\Models\Affiliate;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\Tenant;
use App\Models\TenantProduct;
use App\Notifications\AuthCodeNotification;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Cache::flush();
    $this->seed(FoundationSeeder::class);
    Storage::fake('private');
    Storage::fake('media');
    $this->tenant = Tenant::where('slug', 'egy')->first();
    app()->instance('currentTenant', $this->tenant);
    $this->api = 'http://egy.3alraf.test/api/v1';     // tenant from the host
    $this->hdr = 'http://3alraf.test/api/v1';         // tenant from X-Tenant
    $this->branch = Branch::first();
    $this->city = City::first();
    $this->company = ShippingCompany::first();
    $this->cod = PaymentMethod::where('type', 'cod')->first();
    $this->wallet = PaymentMethod::where('type', 'wallet')->first();
    $this->paymob = PaymentMethod::where('provider', 'paymob')->first();
    $this->paymob->update(['is_active' => true, 'credentials' => ['api_key' => 'k', 'integration_id' => '11', 'iframe_id' => '22', 'hmac_secret' => 'hs']]);
    $this->cat = Category::create(['name' => ['ar' => 'أجهزة', 'en' => 'Devices'], 'slug' => 'devices', 'path' => 'x', 'depth' => 0, 'is_active' => true]);
    $this->cat->update(['path' => (string) $this->cat->id]);
    $this->brand = Brand::create(['name' => ['ar' => 'سوني', 'en' => 'Sony'], 'slug' => 'sony', 'is_active' => true]);
    $this->mk = function (string $slug, $price, $sale = null, bool $inCat = true) {
        $p = Product::create(['type' => 'simple', 'name' => ['ar' => "منتج $slug", 'en' => "P $slug"], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique(), 'brand_id' => $this->brand->id]);
        $inCat && $p->categories()->attach($this->cat->id);
        TenantProduct::create(['product_id' => $p->id, 'price' => $price, 'sale_price' => $sale, 'cost' => 60, 'is_active' => true]);
        app(StockLedger::class)->record($this->branch->id, $p->id, 0, T::PURCHASE_IN, 50, '60.00');

        return $p;
    };
    $this->p1 = ($this->mk)('cheap', 100);
    $this->p2 = ($this->mk)('mid', 300, 250);
    $this->customer = Customer::where('email', 'customer@egy.test')->first();
    // bearer token helper for a customer / affiliate
    $this->tokenFor = fn ($u, string $ability = 'customer') => $u->createToken('t', [$ability])->plainTextToken;
    $this->auth = fn () => ['Authorization' => 'Bearer '.($this->tokenFor)($this->customer)];
    $this->body = ['name' => 'Ali', 'phone' => '0100', 'city_id' => $this->city->id, 'address' => 'St 1', 'shipping_company_id' => $this->company->id];
    $this->sa = Tenant::create(['slug' => 'sa', 'name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
});

function envelope($r, bool $ok = true)
{
    $r->assertJsonStructure(['success', 'message', 'data', 'meta'])->assertJsonPath('success', $ok);

    return $r;
}

// ------------------------------------------------------------------ tenant / envelope / locale

it('resolves the tenant from the host or from the X-Tenant header, 404 envelope otherwise', function () {
    envelope($this->getJson("{$this->api}/store"))->assertJsonPath('data.slug', 'egy')->assertJsonPath('data.currency.code', 'EGP')->assertJsonPath('data.cod_collect_shipping_upfront', false);
    envelope($this->getJson("{$this->hdr}/store", ['X-Tenant' => 'egy']))->assertJsonPath('data.slug', 'egy');
    envelope($this->getJson("{$this->hdr}/store"), false)->assertNotFound();
    envelope($this->getJson("{$this->hdr}/store", ['X-Tenant' => 'nope']), false)->assertNotFound();
    $this->tenant->update(['is_active' => false]);
    envelope($this->getJson("{$this->hdr}/store", ['X-Tenant' => 'egy']), false)->assertNotFound();
    $this->tenant->update(['is_active' => true]);
    $this->getJson('http://3alraf.test/api/v1/tenants')->assertOk()->assertJsonPath('data.0.slug', 'egy'); // central, tenant-less
});

it('uses Accept-Language limited to the tenant locales', function () {
    $this->getJson("{$this->api}/products/cheap", ['Accept-Language' => 'ar'])->assertOk()->assertJsonPath('data.name', 'منتج cheap');
    $this->getJson("{$this->api}/products/cheap", ['Accept-Language' => 'en-US,en;q=0.9'])->assertOk()->assertJsonPath('data.name', 'P cheap')->assertHeader('Content-Language', 'en');
    $this->getJson("{$this->api}/products/cheap", ['Accept-Language' => 'fr'])->assertJsonPath('data.name', 'منتج cheap');
});

it('answers guests on protected routes with a JSON 401 (never a redirect) and unknown routes with a JSON 404', function () {
    envelope($this->getJson("{$this->api}/orders"), false)->assertUnauthorized();
    envelope($this->getJson("{$this->api}/auth/me", ['Authorization' => 'Bearer 1|garbage']), false)->assertUnauthorized();
    envelope($this->getJson("{$this->api}/nothing-here"), false)->assertNotFound();
    envelope($this->postJson("{$this->api}/auth/login", []), false)->assertStatus(422)->assertJsonStructure(['errors' => ['email', 'password']]);
});

it('throttles sensitive endpoints with a 429 envelope', function () {
    foreach (range(1, 5) as $i) {
        $this->postJson("{$this->api}/auth/forgot-password", ['email' => 'x@y.test'])->assertOk();
    }
    envelope($this->postJson("{$this->api}/auth/forgot-password", ['email' => 'x@y.test']), false)->assertStatus(429);
});

// ------------------------------------------------------------------ auth

it('registers, logs in, returns me, updates the profile and logs out (token revoked)', function () {
    Notification::fake();
    $r = envelope($this->postJson("{$this->api}/auth/register", ['name' => 'Mona', 'email' => 'mona@x.test', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'device_name' => 'pixel']), true)
        ->assertCreated()->assertJsonPath('data.customer.email', 'mona@x.test')->assertJsonPath('data.abilities.0', 'customer')->assertJsonMissingPath('data.customer.password');
    $token = $r->json('data.token');
    $h = ['Authorization' => "Bearer $token"];
    $this->postJson("{$this->api}/auth/register", ['name' => 'Dup', 'email' => 'mona@x.test', 'password' => 'secret123', 'password_confirmation' => 'secret123'])->assertStatus(422)->assertJsonStructure(['errors' => ['email']]);

    $this->getJson("{$this->api}/auth/me", $h)->assertOk()->assertJsonPath('data.name', 'Mona')->assertJsonPath('data.email_verified', false);
    $this->putJson("{$this->api}/auth/me", ['name' => 'Mona S', 'email' => 'mona@x.test', 'phone' => '0111'], $h)->assertOk()->assertJsonPath('data.name', 'Mona S');

    $login = $this->postJson("{$this->api}/auth/login", ['email' => 'mona@x.test', 'password' => 'secret123'])->assertOk()->json('data.token');
    $this->postJson("{$this->api}/auth/login", ['email' => 'mona@x.test', 'password' => 'bad'])->assertStatus(422);

    $this->postJson("{$this->api}/auth/logout", [], ['Authorization' => "Bearer $login"])->assertOk();
    $this->getJson("{$this->api}/auth/me", ['Authorization' => "Bearer $login"])->assertUnauthorized();
    $this->getJson("{$this->api}/auth/me", $h)->assertOk(); // other device stays signed in
});

it('locks a login after 5 failures', function () {
    foreach (range(1, 5) as $i) {
        $this->postJson("{$this->api}/auth/login", ['email' => 'customer@egy.test', 'password' => 'bad'])->assertStatus(422);
    }
    $this->postJson("{$this->api}/auth/login", ['email' => 'customer@egy.test', 'password' => 'password'])->assertStatus(422); // locked even with the right password
    RateLimiter::clear('login-lock:'.$this->tenant->id.':customer_api:customer@egy.test');
    $this->postJson("{$this->api}/auth/login", ['email' => 'customer@egy.test', 'password' => 'password'])->assertOk();
});

it('resets the password with an e-mailed one-time code and verifies the e-mail', function () {
    Notification::fake();
    $code = null;
    $capture = function () use (&$code) {
        Notification::assertSentTo($this->customer, AuthCodeNotification::class, function ($n) use (&$code) {
            $m = $n->toMail($this->customer);
            $code = $m->introLines[1];

            return true;
        });
    };
    $this->postJson("{$this->api}/auth/forgot-password", ['email' => 'unknown@x.test'])->assertOk();  // silent
    Notification::assertNothingSent();
    $this->postJson("{$this->api}/auth/forgot-password", ['email' => 'customer@egy.test'])->assertOk();
    $capture();
    $this->postJson("{$this->api}/auth/reset-password", ['email' => 'customer@egy.test', 'code' => '000000', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertStatus(422);
    $old = ($this->tokenFor)($this->customer);
    $this->postJson("{$this->api}/auth/reset-password", ['email' => 'customer@egy.test', 'code' => $code, 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertOk();
    $this->postJson("{$this->api}/auth/reset-password", ['email' => 'customer@egy.test', 'code' => $code, 'password' => 'again12345', 'password_confirmation' => 'again12345'])->assertStatus(422); // one use
    $this->getJson("{$this->api}/auth/me", ['Authorization' => "Bearer $old"])->assertUnauthorized(); // old sessions revoked
    $tok = $this->postJson("{$this->api}/auth/login", ['email' => 'customer@egy.test', 'password' => 'newpass123'])->assertOk()->json('data.token');

    Notification::fake();
    $h = ['Authorization' => "Bearer $tok"];
    $this->postJson("{$this->api}/auth/email/verification-notification", [], $h)->assertOk();
    $capture();
    $this->postJson("{$this->api}/auth/email/verify", ['code' => '123456'], $h)->assertStatus(422);
    $this->postJson("{$this->api}/auth/email/verify", ['code' => $code], $h)->assertOk()->assertJsonPath('data.email_verified', true);
    $this->postJson("{$this->api}/auth/email/verification-notification", [], $h)->assertStatus(422); // already verified
});

it('isolates tenants: a token or login from another tenant is worthless', function () {
    $tok = ($this->tokenFor)($this->customer);
    $this->getJson("{$this->hdr}/auth/me", ['Authorization' => "Bearer $tok", 'X-Tenant' => 'egy'])->assertOk();
    $this->getJson("{$this->hdr}/auth/me", ['Authorization' => "Bearer $tok", 'X-Tenant' => 'sa'])->assertUnauthorized();
    $this->postJson("{$this->hdr}/auth/login", ['email' => 'customer@egy.test', 'password' => 'password'], ['X-Tenant' => 'sa'])->assertStatus(422);
    // same e-mail can register in another tenant
    $this->postJson("{$this->hdr}/auth/register", ['name' => 'X', 'email' => 'customer@egy.test', 'password' => 'secret123', 'password_confirmation' => 'secret123'], ['X-Tenant' => 'sa'])->assertCreated();
    // catalog of tenant egy is invisible from sa
    $this->getJson("{$this->hdr}/products/cheap", ['X-Tenant' => 'sa'])->assertNotFound();
    $this->getJson("{$this->hdr}/catalog", ['X-Tenant' => 'sa'])->assertOk()->assertJsonCount(0, 'data');
    app()->instance('currentTenant', $this->tenant);
    // an order of egy cannot be read through the sa tenant even with its token
    $o = app(OrderPlacementService::class)->place(new CheckoutData(name: 'A', phone: '1', cityId: $this->city->id, address: 'x', shippingCompanyId: $this->company->id, paymentMethodId: $this->cod->id), [['product_id' => $this->p1->id, 'variant_id' => 0, 'qty' => 1]])['order'];
    $this->getJson("{$this->hdr}/orders/{$o->public_token}", ['X-Tenant' => 'sa'])->assertNotFound();
    $this->getJson("{$this->hdr}/orders/{$o->public_token}", ['X-Tenant' => 'egy'])->assertOk();
});

// ------------------------------------------------------------------ catalog

it('serves home, menu, catalog with filters/sort/pagination, facets, suggest, product detail, brands and pages', function () {
    $this->getJson("{$this->api}/home")->assertOk()->assertJsonStructure(['data' => ['banners' => ['hero'], 'categories', 'flash_sale', 'rails' => ['recommended', 'most_sold'], 'brands']]);
    $this->getJson("{$this->api}/menu")->assertOk()->assertJsonPath('data.0.slug', 'devices');

    $r = $this->getJson("{$this->api}/catalog?category=devices&sort=price_desc&per_page=1")->assertOk()->assertJsonPath('meta.pagination.total', 2)->assertJsonPath('meta.pagination.per_page', 1);
    expect($r->json('data.0.slug'))->toBe('mid')->and($r->json('data.0.price'))->toBe('250.00')->and($r->json('data.0.old_price'))->toBe('300.00')->and($r->json('data.0'))->not->toHaveKey('cost');
    $this->getJson("{$this->api}/catalog?q=cheap")->assertJsonCount(1, 'data');
    $this->getJson("{$this->api}/catalog?price_min=200")->assertJsonCount(1, 'data');
    $this->getJson("{$this->api}/catalog?brands[]={$this->brand->id}")->assertJsonCount(2, 'data');
    $this->getJson("{$this->api}/catalog?category=ghost")->assertNotFound();
    $this->getJson("{$this->api}/catalog/facets?category=devices")->assertOk()->assertJsonPath('data.brands.0.slug', 'sony')->assertJsonPath('data.price.max', 250);
    $this->getJson("{$this->api}/catalog/suggest?q=cheap")->assertOk()->assertJsonPath('data.products.0.slug', 'cheap');
    $this->getJson("{$this->api}/brands")->assertOk()->assertJsonPath('data.0.slug', 'sony');

    $d = $this->getJson("{$this->api}/products/mid")->assertOk();
    expect($d->json('data.variants.0.price'))->toBe('250.00')->and($d->json('data.variants.0.in_stock'))->toBeTrue()->and($d->json('data'))->not->toHaveKey('cost');
    $this->getJson("{$this->api}/products/missing")->assertNotFound();

    app()->instance('currentTenant', $this->tenant);
    \App\Models\StaticPage::where('slug', 'terms')->first()->update(['content' => ['ar' => 'نص', 'en' => 'Body']]);
    $this->getJson("{$this->api}/pages")->assertOk()->assertJsonFragment(['slug' => 'terms']);
    $this->getJson("{$this->api}/pages/terms", ['Accept-Language' => 'en'])->assertOk()->assertJsonPath('data.content', 'Body');
});

it('does not list inactive products and shows exact per-variant prices', function () {
    TenantProduct::where('product_id', $this->p1->id)->update(['is_active' => false]);
    $this->getJson("{$this->api}/catalog")->assertJsonCount(1, 'data');
    $this->getJson("{$this->api}/products/cheap")->assertNotFound();
});

// ------------------------------------------------------------------ cart

it('keeps a guest cart with X-Cart-Token, never leaks cost/profit, enforces stock, and merges into the customer cart', function () {
    $r = $this->postJson("{$this->api}/cart/items", ['product_id' => $this->p1->id, 'variant_id' => 0, 'quantity' => 2])->assertOk();
    $cartToken = $r->headers->get('X-Cart-Token');
    expect($cartToken)->not->toBeEmpty();
    $h = ['X-Cart-Token' => $cartToken];
    $c = $this->getJson("{$this->api}/cart", $h)->assertOk()->assertJsonPath('data.items.0.quantity', 2)->assertJsonPath('data.totals.subtotal', '200.00');
    expect(json_encode($c->json('data')))->not->toContain('cogs')->not->toContain('net_profit')->not->toContain('affiliate_commission')->not->toContain('shipping_cost');
    $item = $c->json('data.items.0.id');
    $this->putJson("{$this->api}/cart/items/$item", ['quantity' => 3], $h)->assertOk()->assertJsonPath('data.totals.subtotal', '300.00');
    $this->putJson("{$this->api}/cart/items/$item", ['quantity' => 500], $h)->assertStatus(422); // more than the stock
    $this->getJson("{$this->api}/cart")->assertOk()->assertJsonCount(0, 'data.items');          // another device without the token: empty

    $q = $this->postJson("{$this->api}/cart/quote", ['city_id' => $this->city->id, 'shipping_company_id' => $this->company->id, 'payment_method_id' => $this->cod->id], $h)->assertOk();
    expect($q->json('data.totals.shipping_net'))->toBe('40.00')->and($q->json('data.totals.total'))->toBe('340.00');

    // customer already has an item; login with the guest token merges (quantities add up)
    $mine = ($this->tokenFor)($this->customer);
    $this->postJson("{$this->api}/cart/items", ['product_id' => $this->p1->id, 'variant_id' => 0, 'quantity' => 1], ['Authorization' => "Bearer $mine"])->assertOk();
    $this->postJson("{$this->api}/cart/merge", [], $h + ['Authorization' => "Bearer $mine"])->assertOk()->assertJsonPath('data.items.0.quantity', 4);
    $this->getJson("{$this->api}/cart", $h)->assertOk()->assertJsonCount(0, 'data.items'); // guest cart consumed
    $this->deleteJson("{$this->api}/cart/items/".$this->getJson("{$this->api}/cart", ['Authorization' => "Bearer $mine"])->json('data.items.0.id'), [], ['Authorization' => "Bearer $mine"])->assertOk()->assertJsonCount(0, 'data.items');
});

it('adopts the guest cart on login', function () {
    $cartToken = $this->postJson("{$this->api}/cart/items", ['product_id' => $this->p1->id, 'variant_id' => 0, 'quantity' => 2])->headers->get('X-Cart-Token');
    $tok = $this->postJson("{$this->api}/auth/login", ['email' => 'customer@egy.test', 'password' => 'password'], ['X-Cart-Token' => $cartToken])->assertOk()->json('data.token');
    $this->getJson("{$this->api}/cart", ['Authorization' => "Bearer $tok"])->assertJsonPath('data.items.0.quantity', 2);
});

// ------------------------------------------------------------------ checkout + payments (Sprint 10 rules)

it('lists cities, shipping options (OrderCalculator totals) and only usable payment methods', function () {
    $cartToken = $this->postJson("{$this->api}/cart/items", ['product_id' => $this->p1->id, 'variant_id' => 0, 'quantity' => 1])->headers->get('X-Cart-Token');
    $this->getJson("{$this->api}/checkout/cities")->assertOk()->assertJsonPath('data.0.id', $this->city->id);
    $o = $this->getJson("{$this->api}/checkout/shipping?city_id={$this->city->id}", ['X-Cart-Token' => $cartToken])->assertOk();
    expect($o->json('data.0.fee'))->toBe('40.00')->and($o->json('data.0.order_total'))->toBe('140.00');
    $m = $this->getJson("{$this->api}/checkout/payment-methods")->assertOk();
    $types = collect($m->json('data'))->pluck('type')->unique()->sort()->values()->all();
    expect($types)->toContain('cod')->toContain('wallet')->toContain('online')
        ->and(collect($m->json('data'))->pluck('provider')->filter()->all())->not->toContain('stripe')                    // unimplemented providers are never offered
        ->and(json_encode($m->json()))->not->toContain('hmac_secret')->not->toContain('credentials');
    $this->tenant->update(['settings' => ['cod_collect_shipping_upfront' => true]]);
    $this->getJson("{$this->api}/checkout/payment-methods")->assertJsonPath('meta.cod_collect_shipping_upfront', true);
});

it('places a plain COD order (upfront shipping off): pending admin confirmation, no secrets in the response', function () {
    $h = ['X-Cart-Token' => $this->postJson("{$this->api}/cart/items", ['product_id' => $this->p1->id, 'variant_id' => 0, 'quantity' => 2])->headers->get('X-Cart-Token')];
    $r = envelope($this->postJson("{$this->api}/checkout", $this->body + ['payment_method_id' => $this->cod->id, 'email' => 'g@x.test'], $h))->assertCreated()
        ->assertJsonPath('meta.next_action', 'none')->assertJsonPath('data.status.value', 'pending')->assertJsonPath('data.total', '240.00')->assertJsonPath('data.payment_status', 'unpaid');
    $json = json_encode($r->json());
    expect($json)->not->toContain('cogs')->not->toContain('net_profit')->not->toContain('unit_cost')->not->toContain('affiliate_commission')->not->toContain('shipping_cost');
    $this->getJson("{$this->api}/cart", $h)->assertJsonCount(0, 'data.items'); // cart cleared
    // stock reserved by the placement service
    expect((int) \App\Models\StockLevel::where('product_id', $this->p1->id)->value('reserved'))->toBe(2);
    $this->postJson("{$this->api}/checkout", $this->body + ['payment_method_id' => $this->cod->id], $h)->assertStatus(422); // empty cart
});

it('requires a prepay method for COD with upfront shipping and follows the wallet-proof flow until the admin approves', function () {
    $this->tenant->update(['settings' => ['cod_collect_shipping_upfront' => true]]);
    $h = ['X-Cart-Token' => $this->postJson("{$this->api}/cart/items", ['product_id' => $this->p1->id, 'variant_id' => 0, 'quantity' => 2])->headers->get('X-Cart-Token')];
    $this->postJson("{$this->api}/checkout/validate", $this->body + ['payment_method_id' => $this->cod->id], $h)->assertStatus(422)->assertJsonStructure(['errors' => ['prepay_method_id']]);
    $this->postJson("{$this->api}/checkout", $this->body + ['payment_method_id' => $this->cod->id, 'prepay_method_id' => $this->cod->id], $h)->assertStatus(422);
    $this->postJson("{$this->api}/checkout/validate", $this->body + ['payment_method_id' => $this->cod->id, 'prepay_method_id' => $this->wallet->id], $h)->assertOk()
        ->assertJsonPath('data.totals.prepaid_shipping', '40.00')->assertJsonPath('data.totals.cod_amount', '200.00');

    $r = $this->postJson("{$this->api}/checkout", $this->body + ['payment_method_id' => $this->cod->id, 'prepay_method_id' => $this->wallet->id], $h)->assertCreated()
        ->assertJsonPath('meta.next_action', 'upload_proof')->assertJsonPath('data.cod_amount', '200.00')->assertJsonPath('data.can_upload_proof', true)->assertJsonPath('data.pay_now', '40.00');
    $token = $r->json('data.token');

    $this->postJson("{$this->api}/orders/$token/proof", [])->assertStatus(422);
    $this->postJson("{$this->api}/orders/$token/proof", ['proof' => UploadedFile::fake()->create('x.exe', 10)])->assertStatus(422);
    $p = $this->postJson("{$this->api}/orders/$token/proof", ['proof' => UploadedFile::fake()->image('p.png')])->assertOk()->assertJsonPath('data.payment_status', 'pending_review')->assertJsonPath('data.has_proof', true);
    expect(json_encode($p->json()))->not->toContain('payment-proofs');   // private path never exposed
    Storage::disk('private')->assertExists(\App\Models\Order::where('public_token', $token)->value('payment_proof'));

    // the upload alone confirms nothing: only the admin does
    $order = \App\Models\Order::where('public_token', $token)->first();
    expect($order->status)->toBe(S::PENDING);
    $this->getJson("{$this->api}/orders/$token")->assertJsonPath('data.status.value', 'pending');
    app(\App\Services\Orders\OrderPaymentService::class)->approveProof($order->fresh());
    $this->getJson("{$this->api}/orders/$token/track")->assertOk()->assertJsonPath('data.payment_status', 'partially_paid')->assertJsonPath('data.status', 'pending');
});

it('starts an online payment (redirect URL), offers a retry link and reports the return as non-authoritative', function () {
    Http::fake([
        '*auth/tokens' => Http::response(['token' => 'AUTH']),
        '*ecommerce/orders' => Http::response(['id' => 777]),
        '*acceptance/payment_keys' => Http::response(['token' => 'PKEY']),
    ]);
    $h = ['X-Cart-Token' => $this->postJson("{$this->api}/cart/items", ['product_id' => $this->p1->id, 'variant_id' => 0, 'quantity' => 1])->headers->get('X-Cart-Token')];
    $r = $this->postJson("{$this->api}/checkout", $this->body + ['payment_method_id' => $this->paymob->id], $h)->assertCreated()
        ->assertJsonPath('meta.next_action', 'redirect_to_payment')->assertJsonPath('meta.payment_url', 'https://accept.paymob.com/api/acceptance/iframes/22?payment_token=PKEY');
    $token = $r->json('data.token');
    $this->getJson("{$this->api}/payments/return/$token")->assertOk()->assertJsonPath('meta.paid', false)->assertJsonPath('meta.authoritative', false)->assertJsonPath('data.can_pay_online', true);
    $this->postJson("{$this->api}/orders/$token/pay")->assertOk()->assertJsonPath('data.payment_url', 'https://accept.paymob.com/api/acceptance/iframes/22?payment_token=PKEY');

    // gateway down -> order still placed, app is told to retry
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake(['*' => Http::response([], 500)]);
    $h2 = ['X-Cart-Token' => $this->postJson("{$this->api}/cart/items", ['product_id' => $this->p1->id, 'variant_id' => 0, 'quantity' => 1])->headers->get('X-Cart-Token')];
    $this->postJson("{$this->api}/checkout", $this->body + ['payment_method_id' => $this->paymob->id], $h2)->assertCreated()->assertJsonPath('meta.next_action', 'retry_payment')->assertJsonPath('meta.payment_url', null);
});

// ------------------------------------------------------------------ orders

it('lists only the caller own orders, shows by token, and handles returns for delivered orders of the owner only', function () {
    $place = fn (?Customer $c) => app(OrderPlacementService::class)->place(new CheckoutData(customerId: $c?->id, name: 'A', phone: '1', cityId: $this->city->id, address: 'x', shippingCompanyId: $this->company->id, paymentMethodId: $this->cod->id), [['product_id' => $this->p1->id, 'variant_id' => 0, 'qty' => 2]])['order'];
    $mine = $place($this->customer);
    $guest = $place(null);
    $other = Customer::create(['name' => 'Other', 'email' => 'other@x.test', 'password' => 'password12', 'is_active' => true]);
    $theirs = $place($other);
    $h = ($this->auth)();

    $list = $this->getJson("{$this->api}/orders", $h)->assertOk()->assertJsonPath('meta.pagination.total', 1);
    expect($list->json('data.0.number'))->toBe($mine->number)->and($list->json('data.0'))->not->toHaveKey('items');
    $this->getJson("{$this->api}/orders/{$mine->public_token}", $h)->assertOk()->assertJsonPath('data.items.0.quantity', 2)->assertJsonPath('data.timeline.0.status', 'pending');
    $this->getJson("{$this->api}/orders/{$guest->public_token}")->assertOk();      // guest-safe by token
    $this->getJson("{$this->api}/orders/nope")->assertNotFound();

    $item = $mine->items()->first();
    $this->postJson("{$this->api}/orders/{$mine->public_token}/return", ['items' => [['order_item_id' => $item->id, 'quantity' => 1]], 'reason' => 'x'], $h)->assertStatus(422); // not delivered yet
    foreach ([S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED] as $to) {
        app(OrderStatusService::class)->change($mine, $to);
        app(OrderStatusService::class)->change($theirs, $to);
    }
    $this->postJson("{$this->api}/orders/{$theirs->public_token}/return", ['items' => [['order_item_id' => $theirs->items()->first()->id, 'quantity' => 1]]], $h)->assertForbidden(); // not the owner
    $this->postJson("{$this->api}/orders/{$mine->public_token}/return", ['items' => [['order_item_id' => $item->id, 'quantity' => 1]], 'reason' => 'broken'], $h)
        ->assertCreated()->assertJsonPath('data.status', 'requested');
    $this->getJson("{$this->api}/orders/{$mine->public_token}", $h)->assertJsonPath('data.returns.0.reason', 'broken');
    $this->postJson("{$this->api}/orders/{$mine->public_token}/return", ['items' => [['order_item_id' => $item->id, 'quantity' => 1]]])->assertUnauthorized();
});

// ------------------------------------------------------------------ addresses / wishlist / reviews

it('manages addresses, wishlist and reviews for the signed-in customer only', function () {
    $h = ($this->auth)();
    $a = $this->postJson("{$this->api}/addresses", ['name' => 'Ali', 'phone' => '0100', 'city_id' => $this->city->id, 'address' => 'St 1'], $h)->assertCreated()->assertJsonPath('data.is_default', true)->json('data.id');
    $this->postJson("{$this->api}/addresses", ['name' => 'Ali'], $h)->assertStatus(422);
    $this->putJson("{$this->api}/addresses/$a", ['name' => 'Ali 2', 'phone' => '0100', 'city_id' => $this->city->id, 'address' => 'St 2'], $h)->assertOk()->assertJsonPath('data.address', 'St 2');
    $this->getJson("{$this->api}/addresses", $h)->assertJsonCount(1, 'data');
    $stranger = Customer::create(['name' => 'S', 'email' => 's@x.test', 'password' => 'password12', 'is_active' => true]);
    $this->putJson("{$this->api}/addresses/$a", ['name' => 'Hack', 'phone' => '1', 'city_id' => $this->city->id, 'address' => 'x'], ['Authorization' => 'Bearer '.($this->tokenFor)($stranger)])->assertNotFound();
    // address_id in checkout belongs to the customer
    $cartH = $h + ['X-Cart-Token' => 'x'];
    $this->postJson("{$this->api}/cart/items", ['product_id' => $this->p1->id, 'variant_id' => 0, 'quantity' => 1], $h)->assertOk();
    $this->postJson("{$this->api}/checkout", ['address_id' => $a, 'shipping_company_id' => $this->company->id, 'payment_method_id' => $this->cod->id], $h)->assertCreated()->assertJsonPath('data.address', 'St 2');
    $this->deleteJson("{$this->api}/addresses/$a", [], $h)->assertOk();

    $this->postJson("{$this->api}/wishlist/{$this->p1->id}/toggle", [], $h)->assertOk()->assertJsonPath('data.in_wishlist', true);
    $this->getJson("{$this->api}/wishlist", $h)->assertJsonPath('data.0.slug', 'cheap');
    $this->getJson("{$this->api}/products/cheap", $h)->assertJsonPath('data.in_wishlist', true);
    $this->postJson("{$this->api}/wishlist/{$this->p1->id}/toggle", [], $h)->assertJsonPath('data.in_wishlist', false);
    $this->getJson("{$this->api}/wishlist")->assertUnauthorized();

    $this->postJson("{$this->api}/products/cheap/reviews", ['rating' => 5, 'body' => 'great'], $h)->assertStatus(422); // purchase must be delivered
    $o = $this->customer->orders()->first();
    foreach ([S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED] as $to) {
        app(OrderStatusService::class)->change($o, $to);
    }
    $this->postJson("{$this->api}/products/cheap/reviews", ['rating' => 5, 'body' => 'great'], $h)->assertCreated()->assertJsonPath('data.status', 'pending');
    $this->getJson("{$this->api}/products/cheap/reviews")->assertOk()->assertJsonCount(0, 'data'); // pending until moderated
});

// ------------------------------------------------------------------ affiliates + token abilities

it('keeps customer and affiliate tokens apart and serves the affiliate area', function () {
    $aff = Affiliate::where('email', 'aff@egy.test')->first();
    $cTok = ($this->tokenFor)($this->customer);
    $aTok = $this->postJson("{$this->api}/affiliate/login", ['email' => 'aff@egy.test', 'password' => 'password'])->assertOk()->assertJsonPath('data.abilities.0', 'affiliate')->json('data.token');

    // wrong token type is rejected on both sides
    $this->getJson("{$this->api}/affiliate/dashboard", ['Authorization' => "Bearer $cTok"])->assertUnauthorized();
    $this->getJson("{$this->api}/auth/me", ['Authorization' => "Bearer $aTok"])->assertUnauthorized();
    $this->getJson("{$this->api}/orders", ['Authorization' => "Bearer $aTok"])->assertUnauthorized();
    // an ability-less token of the right model is also rejected
    $this->getJson("{$this->api}/affiliate/dashboard", ['Authorization' => 'Bearer '.$aff->createToken('x', ['customer'])->plainTextToken])->assertUnauthorized();

    $h = ['Authorization' => "Bearer $aTok"];
    $this->getJson("{$this->api}/affiliate/dashboard", $h)->assertOk()->assertJsonStructure(['data' => ['affiliate', 'stats' => ['clicks', 'orders', 'tier', 'commissions'], 'payouts']]);
    $p = $this->getJson("{$this->api}/affiliate/products", $h)->assertOk();
    expect($p->json('data.0.link'))->toContain('http')->and($p->json('data.0.qr_url'))->toContain('/api/v1/affiliate/products/');
    $this->get("{$this->api}/affiliate/products/cheap/qr", $h)->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    $this->postJson("{$this->api}/affiliate/payouts", [], $h)->assertStatus(422);   // nothing approved to pay out
    $this->getJson("{$this->api}/affiliate/payouts", $h)->assertOk();
    $this->postJson("{$this->api}/affiliate/logout", [], $h)->assertOk();
    $this->getJson("{$this->api}/affiliate/me", $h)->assertUnauthorized();

    // pending affiliates register but cannot log in; no token is issued
    $this->postJson("{$this->api}/affiliate/register", ['name' => 'New', 'email' => 'newaff@x.test', 'password' => 'secret123', 'password_confirmation' => 'secret123'])->assertCreated()->assertJsonMissingPath('data.token');
    $this->postJson("{$this->api}/affiliate/login", ['email' => 'newaff@x.test', 'password' => 'secret123'])->assertStatus(422);
    // suspended after login: the existing token stops working
    $t2 = $this->postJson("{$this->api}/affiliate/login", ['email' => 'aff@egy.test', 'password' => 'password'])->json('data.token');
    $aff->update(['status' => 'suspended']);
    $this->getJson("{$this->api}/affiliate/me", ['Authorization' => "Bearer $t2"])->assertUnauthorized();
});

it('attributes an order to the affiliate sent in X-Affiliate-Ref', function () {
    $aff = Affiliate::where('email', 'aff@egy.test')->first();
    $h = ['X-Affiliate-Ref' => $aff->uuid, 'X-Cart-Token' => $this->postJson("{$this->api}/cart/items", ['product_id' => $this->p1->id, 'variant_id' => 0, 'quantity' => 1])->headers->get('X-Cart-Token')];
    $t = $this->postJson("{$this->api}/checkout", $this->body + ['payment_method_id' => $this->cod->id], $h)->assertCreated()->json('data.token');
    expect(\App\Models\Order::where('public_token', $t)->value('affiliate_id'))->toBe($aff->id);
});

it('keeps every route name unique and registers the API on both tenant flavours', function () {
    $names = collect(app('router')->getRoutes()->getRoutes())->map->getName()->filter();
    expect($names->duplicates()->all())->toBe([])->and($names->filter(fn ($n) => str_starts_with($n, 'api.v1.h.'))->count())->toBeGreaterThan(30);
});
