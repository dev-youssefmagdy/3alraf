<?php

use App\DTOs\Orders\CheckoutData;
use App\DTOs\Reports\ReportFilter;
use App\Enums\StockMovementType as T;
use App\Models\Affiliate;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\QrCode;
use App\Models\ShippingCompany;
use App\Models\Supplier;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\TenantProduct;
use App\Services\Orders\OrderPlacementService;
use App\Services\Reports\ReportService;
use App\Services\Retention\SoftDeletePurger;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Storage::fake('media');
    Storage::fake('private');
    $this->tenant = Tenant::where('slug', 'egy')->first();
    app()->instance('currentTenant', $this->tenant);
    codUpfront($this->tenant, false);
    $this->media = Storage::disk('media');
    // age a soft-deleted row: sets deleted_at N days ago without model events
    $this->age = fn (string $table, int $id, int $days) => DB::table($table)->where('id', $id)->update(['deleted_at' => now()->subDays($days)]);
    $this->purge = fn (array $opts = []) => app(SoftDeletePurger::class)->run($opts['days'] ?? null, $opts['dry'] ?? false, $opts['only'] ?? null);
    $this->brand = fn (string $s) => Brand::create(['name' => ['ar' => $s], 'slug' => $s, 'is_active' => true]);
});

it('purges only rows older than the window (59 vs 61 days) and is idempotent', function () {
    $young = ($this->brand)('young');
    $old = ($this->brand)('old');
    DB::table('brands')->whereIn('id', [$young->id, $old->id])->update(['deleted_at' => now()]);
    ($this->age)('brands', $young->id, 59);
    ($this->age)('brands', $old->id, 61);

    $r = ($this->purge)();
    expect($r['brand']['purged'])->toBe(1)
        ->and(Brand::withTrashed()->find($old->id))->toBeNull()
        ->and(Brand::withTrashed()->find($young->id))->not->toBeNull();
    expect(($this->purge)()['brand']['purged'])->toBe(0);
    expect(($this->purge)(['days' => 30])['brand']['purged'])->toBe(1); // --days override
});

it('dry run changes nothing but reports the same counts as a real run', function () {
    $b = ($this->brand)('dry');
    $c = Customer::create(['name' => 'C', 'email' => 'c@x.test', 'password' => 'secret123']);
    DB::table('brands')->where('id', $b->id)->update(['deleted_at' => now()->subDays(90)]);
    DB::table('customers')->where('id', $c->id)->update(['deleted_at' => now()->subDays(90)]);

    $dry = ($this->purge)(['dry' => true]);
    expect(Brand::withTrashed()->find($b->id))->not->toBeNull()
        ->and(Customer::withTrashed()->find($c->id)->name)->toBe('C');
    $real = ($this->purge)();
    expect($real)->toEqual($dry)->and(Brand::withTrashed()->find($b->id))->toBeNull();
});

it('artisan retention:purge works, rejects tenants, logs an audit entry; schedule registered', function () {
    $this->artisan('retention:purge --dry-run')->assertExitCode(0);
    $this->artisan('retention:purge --model=tenant')->assertExitCode(2);
    $this->artisan('retention:purge --model=brand')->assertExitCode(0);
    expect(DB::table('activity_log')->where('log_name', 'retention')->count())->toBe(1);
    $this->artisan('retention:purge --days=10')->assertExitCode(0);
    expect(collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->contains(fn ($e) => str_contains($e->command, 'retention:purge') && $e->withoutOverlapping && $e->onOneServer))->toBeTrue();
});

it('unreferenced product is purged with images, variants, listings and QR files; unique-tombstone rows purge fine', function () {
    $p = Product::create(['type' => 'simple', 'name' => ['ar' => 'م'], 'slug' => 'gone', 'sku' => 'G1', 'barcode' => '2000000000111']);
    $tp = TenantProduct::create(['product_id' => $p->id, 'price' => 100, 'cost' => 50, 'is_active' => true]);
    $this->media->put('catalog/products/a.webp', 'x');
    $this->media->put('catalog/products/a_thumb.webp', 'x');
    $p->images()->create(['path' => 'catalog/products/a.webp', 'thumb_path' => 'catalog/products/a_thumb.webp']);
    $this->media->put("tenants/{$this->tenant->id}/qr/products/p1-a.png", 'png');
    QrCode::create(['owner_type' => TenantProduct::class, 'owner_id' => $tp->id, 'url' => 'u', 'url_hash' => sha1('u'), 'path' => "tenants/{$this->tenant->id}/qr/products/p1-a.png"]);
    $p->delete();
    ($this->age)('products', $p->id, 70);

    $code = DiscountCode::create(['code' => 'SAVE', 'type' => 'percent', 'value' => 10, 'is_active' => true]);
    $code->delete(); // tombstones the code
    ($this->age)('discount_codes', $code->id, 70);

    $r = ($this->purge)();
    expect($r['product']['purged'])->toBe(1)->and($r['discount_code']['purged'])->toBe(1)
        ->and(Product::withTrashed()->find($p->id))->toBeNull()
        ->and(DB::table('tenant_products')->where('product_id', $p->id)->count())->toBe(0)
        ->and(DB::table('images')->count())->toBe(0)
        ->and(QrCode::withoutGlobalScopes()->count())->toBe(0);
    expect($this->media->allFiles())->toBe([]);
});

it('a product/variant/branch/method/city/company/code referenced by a retained order is kept and report numbers do not change', function () {
    $city = City::first();
    $company = ShippingCompany::first();
    $cod = PaymentMethod::where('type', 'cod')->first();
    $branch = Branch::first();
    $product = Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج'], 'slug' => 'ret1', 'sku' => 'R1', 'barcode' => '2000000000222']);
    TenantProduct::create(['product_id' => $product->id, 'price' => 100, 'cost' => 60, 'is_active' => true]);
    app(StockLedger::class)->record($branch->id, $product->id, 0, T::PURCHASE_IN, 50, '60.00');
    $order = app(OrderPlacementService::class)->place(new CheckoutData(
        name: 'Ali', phone: '0100', cityId: $city->id, address: 'St', shippingCompanyId: $company->id, paymentMethodId: $cod->id,
    ), [['product_id' => $product->id, 'variant_id' => 0, 'qty' => 2]])['order'];
    DB::table('orders')->where('id', $order->id)->update(['status' => 'confirmed']);

    $filter = new ReportFilter(now()->subDay()->toDateString(), now()->addDay()->toDateString(), false, [$this->tenant->id]);
    $reports = fn () => collect(ReportService::KEYS)->mapWithKeys(fn ($k) => [$k => app(ReportService::class)->run($k, $filter)])->all();
    $before = $reports();

    foreach ([[$product, 'products'], [$city, 'cities'], [$company, 'shipping_companies'], [$cod, 'payment_methods'], [$branch, 'branches']] as [$m, $t]) {
        DB::table($t)->where('id', $m->id)->update(['deleted_at' => now()->subDays(200)]);
    }
    $r = ($this->purge)();

    foreach (['product', 'city', 'shipping_company', 'payment_method', 'branch'] as $k) {
        expect($r[$k]['purged'])->toBe(0, $k)->and($r[$k]['retained'])->toBeGreaterThan(0, $k);
    }
    expect(DB::table('order_items')->where('product_id', $product->id)->count())->toBe(1)
        ->and(DB::table('stock_movements')->where('product_id', $product->id)->count())->toBeGreaterThan(0)
        ->and($reports())->toEqual($before);
});

it('variant referenced by order items is kept, an unreferenced one is purged', function () {
    $p = Product::create(['type' => 'variable', 'name' => ['ar' => 'م'], 'slug' => 'vv', 'sku' => 'VV']);
    $a = $p->variants()->create(['combination_hash' => 'a', 'sku' => 'VA', 'barcode' => '2000000000301', 'is_active' => true]);
    $b = $p->variants()->create(['combination_hash' => 'b', 'sku' => 'VB', 'barcode' => '2000000000302', 'is_active' => true]);
    app(StockLedger::class)->record(Branch::first()->id, $p->id, $a->id, T::PURCHASE_IN, 5, '10.00');
    foreach ([$a, $b] as $v) {
        DB::table('product_variants')->where('id', $v->id)->update(['deleted_at' => now()->subDays(90)]);
    }
    $r = ($this->purge)();
    expect($r['variant'])->toMatchArray(['purged' => 1, 'retained' => 1])
        ->and(ProductVariant::withTrashed()->find($a->id))->not->toBeNull()
        ->and(ProductVariant::withTrashed()->find($b->id))->toBeNull();
});

it('customer with orders is anonymised (order totals untouched), customer without is purged, other tenants and live rows untouched', function () {
    $withOrder = Customer::create(['name' => 'Real Name', 'email' => 'real@x.test', 'phone' => '0111', 'password' => 'secret123']);
    $plain = Customer::create(['name' => 'Plain', 'email' => 'plain@x.test', 'password' => 'secret123']);
    $live = Customer::create(['name' => 'Live', 'email' => 'live@x.test', 'password' => 'secret123']);
    $withOrder->addresses()->create(['name' => 'Real', 'phone' => '1', 'city_id' => City::first()->id, 'address' => 'a', 'label' => 'home']);
    $order = Order::withoutGlobalScopes()->first() ?? null;
    $city = City::first();
    DB::table('orders')->insert([
        'tenant_id' => $this->tenant->id, 'number' => 'RET-1', 'public_token' => (string) \Illuminate\Support\Str::uuid(), 'customer_id' => $withOrder->id,
        'customer_name' => 'Real Name', 'customer_phone' => '0111', 'address' => 'St', 'city_id' => $city->id, 'branch_id' => Branch::first()->id,
        'status' => 'delivered', 'subtotal' => 100, 'total' => 100, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $withOrder->delete();
    $plain->delete();
    ($this->age)('customers', $withOrder->id, 80);
    ($this->age)('customers', $plain->id, 80);

    $r = ($this->purge)();
    expect($r['customer'])->toMatchArray(['purged' => 1, 'anonymized' => 1]);
    $c = Customer::withTrashed()->find($withOrder->id);
    expect($c->name)->toBe('Anonymized customer')->and($c->phone)->toBeNull()->and($c->email)->toEndWith('@anonymized.invalid')->and($c->is_active)->toBeFalse()
        ->and(DB::table('customer_addresses')->where('customer_id', $c->id)->count())->toBe(0)
        ->and(Customer::withTrashed()->find($plain->id))->toBeNull()
        ->and(Customer::find($live->id))->not->toBeNull()
        ->and((float) DB::table('orders')->where('number', 'RET-1')->value('total'))->toBe(100.0);
    expect(($this->purge)()['customer'])->toMatchArray(['purged' => 0, 'anonymized' => 0, 'retained' => 1]); // idempotent
});

it('soft-deleted order is purged with its proof; one with returns/commission is kept and anonymised (report-safe)', function () {
    $mk = fn (string $n) => DB::table('orders')->insertGetId([
        'tenant_id' => $this->tenant->id, 'number' => $n, 'public_token' => (string) \Illuminate\Support\Str::uuid(), 'customer_name' => 'Secret '.$n,
        'customer_phone' => '0100', 'customer_email' => 'x@y.z', 'address' => 'Home', 'payment_proof' => "tenants/{$this->tenant->id}/payment-proofs/$n.jpg",
        'city_id' => City::first()->id, 'branch_id' => Branch::first()->id, 'status' => 'cancelled', 'subtotal' => 50, 'total' => 50,
        'deleted_at' => now()->subDays(100), 'created_at' => now()->subDays(100), 'updated_at' => now(),
    ]);
    $free = $mk('O-FREE');
    $held = $mk('O-HELD');
    Storage::disk('private')->put("tenants/{$this->tenant->id}/payment-proofs/O-FREE.jpg", 'p');
    Storage::disk('private')->put("tenants/{$this->tenant->id}/payment-proofs/O-HELD.jpg", 'p');
    $aff = Affiliate::first();
    DB::table('affiliate_commissions')->insert(['tenant_id' => $this->tenant->id, 'affiliate_id' => $aff->id, 'order_id' => $held, 'base' => 50, 'percent' => 5, 'amount' => 2.5, 'status' => 'voided', 'created_at' => now(), 'updated_at' => now()]);

    $r = ($this->purge)();
    expect($r['order'])->toMatchArray(['purged' => 1, 'anonymized' => 1])
        ->and(DB::table('orders')->where('id', $free)->exists())->toBeFalse()
        ->and(DB::table('orders')->where('id', $held)->value('customer_name'))->toBe('Anonymized')
        ->and(DB::table('orders')->where('id', $held)->value('address'))->toBe('-')
        ->and(DB::table('affiliate_commissions')->where('order_id', $held)->count())->toBe(1);
    Storage::disk('private')->assertMissing("tenants/{$this->tenant->id}/payment-proofs/O-FREE.jpg");
    Storage::disk('private')->assertMissing("tenants/{$this->tenant->id}/payment-proofs/O-HELD.jpg");
});

it('brand/category/tag/supplier rules: kept while referenced, purged (with files) when free', function () {
    $brandUsed = ($this->brand)('used');
    $brandFree = ($this->brand)('free');
    $this->media->put('catalog/brands/l.webp', 'x');
    $brandFree->update(['logo' => 'catalog/brands/l.webp']);
    Product::create(['type' => 'simple', 'name' => ['ar' => 'p'], 'slug' => 'bp', 'sku' => 'BP', 'brand_id' => $brandUsed->id, 'barcode' => '2000000000401']);
    $catFree = Category::create(['name' => ['ar' => 'c'], 'slug' => 'cf', 'path' => '1', 'depth' => 0]);
    $tag = Tag::create(['name' => ['ar' => 't'], 'slug' => 't']);
    $sup = Supplier::create(['name' => 'S']);
    $supUsed = Supplier::create(['name' => 'S2']);
    DB::table('purchase_orders')->insert(['tenant_id' => $this->tenant->id, 'supplier_id' => $supUsed->id, 'branch_id' => Branch::first()->id, 'number' => 'PO-R', 'status' => 'received', 'ordered_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    foreach ([[$brandUsed, 'brands'], [$brandFree, 'brands'], [$catFree, 'categories'], [$tag, 'tags'], [$sup, 'suppliers'], [$supUsed, 'suppliers']] as [$m, $t]) {
        DB::table($t)->where('id', $m->id)->update(['deleted_at' => now()->subDays(70)]);
    }
    $r = ($this->purge)();
    expect($r['brand'])->toMatchArray(['purged' => 1, 'retained' => 1])
        ->and($r['category']['purged'])->toBe(1)->and($r['tag']['purged'])->toBe(1)
        ->and($r['supplier'])->toMatchArray(['purged' => 1, 'retained' => 1]);
    expect($this->media->allFiles())->toBe([]);
});

it('--model limits the run and tenants are never purged', function () {
    $b = ($this->brand)('lim');
    $t = Tenant::create(['slug' => 'dead', 'name' => 'D', 'country' => 'EG', 'currency_code' => 'EGP', 'currency_symbol' => 'EGP', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar'], 'timezone' => 'Africa/Cairo', 'is_active' => true]);
    DB::table('brands')->where('id', $b->id)->update(['deleted_at' => now()->subDays(99)]);
    DB::table('tenants')->where('id', $t->id)->update(['deleted_at' => now()->subDays(999)]);
    $r = ($this->purge)(['only' => ['city']]);
    expect(array_keys($r))->toBe(['city'])->and(Brand::withTrashed()->find($b->id))->not->toBeNull();
    ($this->purge)();
    expect(Tenant::withTrashed()->find($t->id))->not->toBeNull()->and(config('retention.models'))->not->toHaveKey('tenant');
});
