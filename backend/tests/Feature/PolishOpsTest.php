<?php

use App\DTOs\Orders\CheckoutData;
use App\Enums\OrderStatus as S;
use App\Enums\StockMovementType as T;
use App\Models\Attribute;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Models\TenantProductVariant;
use App\Services\Lists\ProductListResolver;
use App\Services\Orders\CarrierSettlementService;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Cache::flush();
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->t = 'http://egy.3alraf.test/admin';
    $this->s = 'http://egy.3alraf.test/en';
    app()->instance('currentTenant', $this->tenant);
    $this->branch = Branch::first();
    $this->city = City::first();
    $this->company = ShippingCompany::first();
    $this->cod = PaymentMethod::where('type', 'cod')->first();
    $this->brand = Brand::create(['name' => ['ar' => 'سوني', 'en' => 'Sony'], 'slug' => 'sony', 'is_active' => true]);
    $this->mk = function (string $slug, $price, array $name = [], ?Brand $brand = null) {
        $p = Product::create(['type' => 'simple', 'name' => $name ?: ['ar' => "منتج $slug", 'en' => "Item $slug"], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique(), 'brand_id' => $brand?->id]);
        TenantProduct::create(['product_id' => $p->id, 'price' => $price, 'cost' => 10, 'is_active' => true]);
        app(StockLedger::class)->record($this->branch->id, $p->id, 0, T::PURCHASE_IN, 50, '10.00');

        return $p;
    };
});

it('manages tags centrally, assigns them to products and serves brand and tag pages with the tag filter', function () {
    $a = ($this->mk)('zz-alpha', 100, [], $this->brand);
    $b = ($this->mk)('zz-beta', 200);
    $this->actingAs(\App\Models\CentralAdmin::first(), 'central');
    $c = 'http://3alraf.test/admin';
    $this->postJson("$c/tags/validate", [])->assertStatus(422)->assertJsonValidationErrors(['name.ar']);
    $this->post("$c/tags", ['name' => ['ar' => 'جديد', 'en' => 'Fresh'], 'slug' => 'fresh', 'is_active' => 1], ['Accept' => 'application/json'])->assertOk();
    $tag = Tag::first();
    expect($tag->slug)->toBe('fresh');
    $this->getJson("$c/tags/datatable?draw=1&start=0&length=10")->assertOk()->assertJsonPath('recordsTotal', 1);
    $this->get("$c/tags")->assertOk();

    $a->tags()->sync([$tag->id]);
    $this->actingAs(\App\Models\CentralAdmin::first(), 'central')->get("$c/products/{$a->id}/edit")->assertOk()->assertSee('tag_ids[]', false);

    $this->get("{$this->s}/tag/fresh")->assertOk()->assertSee('Item zz-alpha')->assertDontSee('Item zz-beta');
    $this->get("{$this->s}/brand/sony")->assertOk()->assertSee('Item zz-alpha')->assertDontSee('Item zz-beta');
    $this->get("{$this->s}/brand/nope")->assertNotFound();
    $this->get("{$this->s}/search?q=zz&tags[]={$tag->id}")->assertOk()->assertSee('Item zz-alpha')->assertDontSee('Item zz-beta');
    $tag->update(['is_active' => false]);
    $this->get("{$this->s}/tag/fresh")->assertNotFound();

    $this->delete("$c/tags/{$tag->id}", [], ['Accept' => 'application/json'])->assertOk();
    expect(Tag::count())->toBe(0)->and(DB::table('product_tag')->count())->toBe(0);
});

it('searches names with FULLTEXT (ar + en prefix), falls back to LIKE for short words and matches SKU prefixes', function () {
    ($this->mk)('hp1', 100, ['ar' => 'سماعة لاسلكية', 'en' => 'Wireless Headphones'], null);
    ($this->mk)('kb1', 100, ['ar' => 'لوحة مفاتيح', 'en' => 'Mechanical Keyboard'], null);
    DB::commit(); // InnoDB FULLTEXT only sees committed rows; everything is wiped again in the finally below
    try {
        $search = fn (string $q) => collect($this->getJson("{$this->s}/search/suggest?q=".urlencode($q))->assertOk()->json('products'))->pluck('name')->all();

        expect($search('headph'))->toBe(['Wireless Headphones']);              // prefix, boolean mode
        expect($search('wireless head'))->toBe(['Wireless Headphones']);       // every word required
        expect($search('mechanical wireless'))->toBe([]);
        expect($this->get("{$this->s}/search?q=".urlencode('سماعة'))->assertOk()->getContent())->toContain('Wireless Headphones')->not->toContain('Mechanical Keyboard');
        expect($this->get("{$this->s}/search?q=".urlencode('لوحة'))->getContent())->toContain('Mechanical Keyboard');
        expect($search('ke'))->toBe(['Mechanical Keyboard']);                  // < 3 chars: LIKE fallback
        expect($search('HP1'))->toBe(['Wireless Headphones']);                 // sku prefix, case-insensitive
        expect($search('%'))->toBe([]);                                        // wildcard is escaped
        // injection-ish input must not break the boolean query
        $this->get("{$this->s}/search?q=".urlencode('+-*"() ~<>'))->assertOk();
    } finally {
        Schema::disableForeignKeyConstraints();
        foreach (array_map(fn ($r) => array_values((array) $r)[0], DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')) as $table) {
            if ($table !== 'migrations') {
                DB::table($table)->truncate();
            }
        }
        Schema::enableForeignKeyConstraints();
    }
});

it('shows the lowest variant price with a "from" marker on cards', function () {
    $attr = Attribute::create(['name' => ['ar' => 'مقاس', 'en' => 'Size'], 'code' => 'size', 'type' => 'select']);
    $v1 = $attr->values()->create(['value' => ['ar' => 'S', 'en' => 'S'], 'sort_order' => 1]);
    $v2 = $attr->values()->create(['value' => ['ar' => 'L', 'en' => 'L'], 'sort_order' => 2]);
    $p = Product::create(['type' => 'variable', 'name' => ['ar' => 'قميص', 'en' => 'Shirt'], 'slug' => 'shirt', 'sku' => 'SHIRT']);
    $tp = TenantProduct::create(['product_id' => $p->id, 'price' => 100, 'cost' => 10, 'is_active' => true]);
    $var = [];
    foreach ([[$v1, 'S'], [$v2, 'L']] as $i => [$v, $l]) {
        $var[$l] = $p->variants()->create(['combination_hash' => (string) $v->id, 'sku' => "SH-$l", 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique(), 'is_active' => true]);
        $var[$l]->values()->sync([$v->id]);
    }
    TenantProductVariant::create(['tenant_product_id' => $tp->id, 'product_variant_id' => $var['S']->id, 'price' => 80, 'cost' => 10, 'is_active' => true]);
    TenantProductVariant::create(['tenant_product_id' => $tp->id, 'product_variant_id' => $var['L']->id, 'price' => 120, 'cost' => 10, 'is_active' => true]);

    $d = app(\App\Services\Storefront\CardPresenter::class)->decorate(collect([$tp->fresh()]))->first()->display;
    expect($d['price'])->toBe('80.00')->and($d['from'])->toBeTrue();
    $this->get("{$this->s}/search?q=shirt")->assertOk()->assertSee('From')->assertSee('80.00');
    // an inactive cheap variant is ignored
    TenantProductVariant::where('product_variant_id', $var['S']->id)->update(['is_active' => false]);
    $d = app(\App\Services\Storefront\CardPresenter::class)->decorate(collect([$tp->fresh()]))->first()->display;
    expect($d['price'])->toBe('120.00')->and($d['from'])->toBeFalse();
});

it('renders the footer from ACTIVE payment methods only, with app badges from settings, and caches it', function () {
    PaymentMethod::where('type', 'wallet')->update(['is_active' => false]);
    $this->tenant->update(['settings' => ['app_links' => ['ios' => 'https://apps.apple.com/app/x', 'android' => null]]]);
    Cache::flush();
    $html = $this->get("{$this->s}")->assertOk()->getContent();
    expect($html)->toContain('https://apps.apple.com/app/x')->and($html)->toContain('Google Play')->and($html)->toContain('Cash on delivery');
    expect($html)->not->toContain('Vodafone');
    $queries = 0;
    DB::listen(function ($q) use (&$queries) {
        if (str_contains($q->sql, 'static_pages') || str_contains($q->sql, 'payment_methods')) {
            $queries++;
        }
    });
    $this->get("{$this->s}")->assertOk();
    expect($queries)->toBe(0);
});

it('builds category rails with a constant number of queries', function () {
    foreach (range(1, 6) as $i) {
        $c = Category::create(['name' => ['ar' => "قسم $i", 'en' => "Cat $i"], 'slug' => "cat-$i", 'path' => 'x', 'depth' => 0, 'is_active' => true]);
        $c->update(['path' => (string) $c->id]);
        $p = ($this->mk)("p$i", 10 * $i);
        $p->categories()->attach($c->id);
    }
    Cache::flush();
    $count = 0;
    DB::listen(function () use (&$count) {
        $count++;
    });
    $home = app(\App\Services\Storefront\StorefrontData::class)->home();
    expect($home['categoryRails'])->toHaveCount(4)->and($count)->toBeLessThan(40);
});

it('caches the sold / review rankings per tenant for five minutes and caps them at 200', function () {
    $p = ($this->mk)('top', 100);
    $order = app(OrderPlacementService::class)->place(new CheckoutData(name: 'A', phone: '010', cityId: $this->city->id, address: 'x', shippingCompanyId: $this->company->id, paymentMethodId: $this->cod->id), [['product_id' => $p->id, 'variant_id' => 0, 'qty' => 1]])['order'];
    $order->forceFill(['status' => S::DELIVERED])->save();
    $r = app(ProductListResolver::class);
    expect($r->soldRanking())->toBe([$p->id]);
    expect(Cache::has('rank.sold.'.$this->tenant->id))->toBeTrue()->and(ProductListResolver::RANK_LIMIT)->toBe(200)->and(ProductListResolver::RANK_TTL)->toBe(300);
    $order->forceFill(['status' => S::CANCELLED])->save();
    expect($r->soldRanking())->toBe([$p->id]);   // still cached
    Cache::flush();
    expect($r->soldRanking())->toBe([]);
});

it('exports the order invoice as a PDF for admins and for the public token', function () {
    $p = ($this->mk)('inv', 100);
    $order = app(OrderPlacementService::class)->place(new CheckoutData(name: 'علي', phone: '010', cityId: $this->city->id, address: 'شارع ١', shippingCompanyId: $this->company->id, paymentMethodId: $this->cod->id), [['product_id' => $p->id, 'variant_id' => 0, 'qty' => 1]])['order'];
    $res = $this->get("{$this->s}/order/{$order->public_token}/invoice.pdf")->assertOk();
    expect($res->headers->get('content-type'))->toContain('pdf')->and(substr($res->getContent() ?: $res->streamedContent(), 0, 4))->toBe('%PDF');
    $this->get("{$this->s}/order/not-a-token/invoice.pdf")->assertNotFound();
    $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $this->get("{$this->t}/orders/{$order->id}/invoice.pdf")->assertOk();
    // Arabic locale renders too (shaped text)
    app()->setLocale('ar');
    expect(strlen(app(\App\Services\Orders\InvoicePdf::class)->render($order)->output()))->toBeGreaterThan(1000);
});

it('writes an audit trail for orders, prices, roles and shows it only to the owning tenant', function () {
    $p = ($this->mk)('aud', 100);
    $owner = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
    $this->actingAs($owner, 'tenant');
    $order = app(OrderPlacementService::class)->place(new CheckoutData(name: 'A', phone: '010', cityId: $this->city->id, address: 'x', shippingCompanyId: $this->company->id, paymentMethodId: $this->cod->id), [['product_id' => $p->id, 'variant_id' => 0, 'qty' => 1]])['order'];
    app(OrderStatusService::class)->change($order->fresh(), S::CONFIRMED);
    $log = Activity::where('subject_type', Order::class)->where('subject_id', $order->id)->where('event', 'updated')->get()->first(fn ($a) => isset($a->properties['attributes']['status']));
    expect($log)->not->toBeNull()->and($log->tenant_id)->toBe($this->tenant->id)->and($log->causer_id)->toBe($owner->id)->and($log->properties['attributes']['status'])->toBe('confirmed');

    TenantProduct::where('product_id', $p->id)->first()->update(['price' => 120]);
    $priceLog = Activity::where('log_name', 'tenantproduct')->where('event', 'updated')->latest('id')->first();
    expect($priceLog->properties['old']['price'])->toBe('100.00')->and($priceLog->properties['attributes']['price'])->toBe('120.00');

    $this->postJson("{$this->t}/roles", ['name' => 'Packers', 'permissions' => ['orders.view']])->assertOk();
    expect(Activity::where('log_name', 'access')->where('event', 'role.saved')->where('tenant_id', $this->tenant->id)->count())->toBe(1);

    $this->get("{$this->t}/audit")->assertOk();
    $this->getJson("{$this->t}/audit/datatable?draw=1&start=0&length=50")->assertOk()->assertJson(fn ($j) => $j->where('recordsTotal', Activity::where('tenant_id', $this->tenant->id)->count())->etc());
    Activity::create(['log_name' => 'x', 'description' => 'other tenant', 'tenant_id' => 9999]);
    $this->getJson("{$this->t}/audit/datatable?draw=1&start=0&length=50")->assertOk()->assertJson(fn ($j) => $j->where('recordsTotal', Activity::where('tenant_id', $this->tenant->id)->count())->etc());
});

it('soft deletes orders, customers and discount codes and hides them from reports', function () {
    $p = ($this->mk)('sd', 100);
    $order = app(OrderPlacementService::class)->place(new CheckoutData(name: 'A', phone: '010', cityId: $this->city->id, address: 'x', shippingCompanyId: $this->company->id, paymentMethodId: $this->cod->id), [['product_id' => $p->id, 'variant_id' => 0, 'qty' => 1]])['order'];
    $code = DiscountCode::create(['code' => 'X1', 'type' => 'percent', 'value' => 5, 'is_active' => true]);
    $cust = Customer::create(['name' => 'C', 'email' => 'c@x.test', 'password' => 'secret123', 'is_active' => true]);
    $order->forceFill(['customer_id' => $cust->id, 'discount_code_id' => $code->id, 'status' => S::CONFIRMED])->save();
    $code->delete();
    $cust->delete();
    expect(DiscountCode::where('code', 'X1')->count())->toBe(0)->and(DiscountCode::withTrashed()->where('code', 'X1')->count())->toBe(1)->and(Customer::where('email', 'c@x.test')->count())->toBe(0);
    expect(Order::find($order->id)->customer->email)->toBe('c@x.test')->and(Order::find($order->id)->discountCode->code)->toBe('X1'); // history keeps its links

    $f = new \App\DTOs\Reports\ReportFilter(now()->subDay()->toDateString(), now()->toDateString(), false, [$this->tenant->id]);
    $before = app(\App\Repositories\Contracts\ReportRepositoryInterface::class)->sales($f);
    expect(array_sum(array_column($before, 'orders')))->toBe(1);
    $order->delete();
    expect(Order::count())->toBe(0)->and(Order::withTrashed()->count())->toBe(1);
    $after = app(\App\Repositories\Contracts\ReportRepositoryInterface::class)->sales($f);
    expect(array_sum(array_column($after, 'orders')))->toBe(0);
});

it('marks delivered COD orders of a carrier as settled and moves them out of the outstanding balance', function () {
    $p = ($this->mk)('cod', 100);
    $this->tenant->update(['settings' => ['cod_collect_shipping_upfront' => false]]);
    app()->instance('currentTenant', $this->tenant->fresh());
    $order = app(OrderPlacementService::class)->place(new CheckoutData(name: 'A', phone: '010', cityId: $this->city->id, address: 'x', shippingCompanyId: $this->company->id, paymentMethodId: $this->cod->id), [['product_id' => $p->id, 'variant_id' => 0, 'qty' => 1]])['order'];
    expect((float) $order->cod_amount)->toBeGreaterThan(0);
    $order->forceFill(['status' => S::DELIVERED, 'delivered_at' => now()])->save();
    $f = new \App\DTOs\Reports\ReportFilter(now()->subDay()->toDateString(), now()->toDateString(), false, [$this->tenant->id]);
    $repo = app(\App\Repositories\Contracts\ReportRepositoryInterface::class);
    $row = $repo->codSettlement($f)[0];
    expect((float) $row['owed'])->toBeGreaterThan(0)->and((float) $row['settled'])->toBe(0.0)->and((int) $row['company_id'])->toBe($this->company->id);

    $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $this->post("{$this->t}/reports/cod-settlement/settle", ['shipping_company_id' => $this->company->id, 'from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()])->assertRedirect();
    expect($order->fresh()->carrier_settled_at)->not->toBeNull();
    $row2 = $repo->codSettlement($f)[0];
    expect((float) $row2['owed'])->toBe(0.0)->and((float) $row2['settled'])->toBe((float) $row['owed']);
    expect(app(CarrierSettlementService::class)->settle($this->company->id, now()->subDay()->toDateString(), now()->toDateString()))->toBe(0); // idempotent
    $this->get("{$this->t}/reports/cod-settlement")->assertOk();
});

it('creates a store from the command with owner, COD method, default branch and affiliate tiers', function () {
    $this->artisan('tenants:create', ['slug' => 'newshop', 'name' => 'New Shop', '--owner-email' => 'boss@newshop.test', '--country' => 'EG', '--timezone' => 'Africa/Cairo'])
        ->expectsOutputToContain('Tenant newshop created')->assertSuccessful();
    $t = Tenant::where('slug', 'newshop')->first();
    expect($t->default_locale)->toBe('ar')->and($t->is_active)->toBeTrue();
    $admin = TenantAdmin::withoutGlobalScopes()->where('tenant_id', $t->id)->first();
    expect($admin->email)->toBe('boss@newshop.test')->and($admin->hasRole('tenant-owner'))->toBeTrue();
    expect(PaymentMethod::withoutGlobalScopes()->where('tenant_id', $t->id)->where('type', 'cod')->count())->toBe(1)
        ->and(Branch::withoutGlobalScopes()->where('tenant_id', $t->id)->where('is_default', true)->count())->toBe(1)
        ->and(\App\Models\AffiliateTier::withoutGlobalScopes()->where('tenant_id', $t->id)->orderBy('min_sales')->pluck('percent')->map(fn ($x) => (int) $x)->all())->toBe([5, 7, 10]);
    $this->artisan('tenants:create', ['slug' => 'newshop', 'name' => 'Dup', '--owner-email' => 'x@y.test'])->assertFailed();
    $this->artisan('tenants:create', ['slug' => 'www', 'name' => 'Reserved', '--owner-email' => 'x@y.test'])->assertFailed();
    $this->artisan('tenants:create', ['slug' => 'okslug', 'name' => 'No owner'])->assertFailed();
});

it('answers the central health endpoint and the framework /up probe', function () {
    $this->getJson('http://3alraf.test/api/v1/health')->assertOk()->assertJsonPath('status', 'ok')->assertJsonPath('checks.database', 'ok');
    $this->get('http://3alraf.test/up')->assertOk();
});

it('ships well-formed ops files', function () {
    foreach (['Dockerfile', 'docker-compose.yml', 'deploy/nginx/app.conf', 'deploy/nginx/cdn.conf', 'deploy/supervisor/3alraf.conf', '.env.production.example', '../.github/workflows/ci.yml'] as $f) {
        expect(file_exists(base_path($f)))->toBeTrue($f);
    }
    $env = file_get_contents(base_path('.env.production.example'));
    expect($env)->toContain('SESSION_SECURE_COOKIE=true')->and($env)->not->toMatch('/^SESSION_DOMAIN=/m')->and($env)->toContain('QUEUE_CONNECTION=redis');
    $nginx = file_get_contents(base_path('deploy/nginx/app.conf'));
    expect($nginx)->toContain('server_name domain.com *.domain.com;');
    expect(substr_count($nginx, '{'))->toBe(substr_count($nginx, '}'));
});
