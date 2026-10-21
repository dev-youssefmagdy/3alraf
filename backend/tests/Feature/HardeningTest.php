<?php

use App\DTOs\Orders\CheckoutData;
use App\Enums\StockMovementType as T;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Models\City;
use App\Services\Orders\OrderPlacementService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->egy = Tenant::where('slug', 'egy')->first();
    codUpfront($this->egy, false);   // legacy tests: carrier collects the full total
    $this->central = 'http://3alraf.test';
    $this->t = 'http://egy.3alraf.test/admin';
    $this->s = 'http://egy.3alraf.test/ar';
    $this->mkTenant = fn (string $slug, bool $active = true) => Tenant::create(['slug' => $slug, 'name' => strtoupper($slug).' Store', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => $active]);
});

// ---------------------------------------------------------------- central site
it('lists only ACTIVE tenants on the central landing page and exposes them as JSON', function () {
    ($this->mkTenant)('sa');
    ($this->mkTenant)('closed', false);

    $this->get($this->central.'/')->assertOk()->assertSee('Egypt Store')->assertSee('SA Store')->assertDontSee('CLOSED Store')->assertSee('dir="rtl"', false);
    $this->get($this->central.'/admin/login')->assertOk();   // admin stays at /admin

    $json = $this->getJson($this->central.'/api/v1/tenants')->assertOk()->json('data');
    expect(collect($json)->pluck('slug')->all())->toEqualCanonicalizing(['egy', 'sa']);
    $egy = collect($json)->firstWhere('slug', 'egy');
    expect(array_keys($egy))->toEqualCanonicalizing(['slug', 'name', 'country', 'currency', 'default_locale', 'locales', 'base_url'])
        ->and($egy['currency'])->toBe('EGP')->and($egy['base_url'])->toBe('http://egy.3alraf.test/');
});

it('sets the preferred_tenant cookie on choice, redirects to the store default locale and refuses inactive tenants', function () {
    ($this->mkTenant)('closed', false);

    $this->get($this->central.'/choose/egy')->assertRedirect('http://egy.3alraf.test/ar')->assertCookie('preferred_tenant', 'egy');
    $this->get($this->central.'/choose/closed')->assertNotFound();
    $this->get($this->central.'/choose/nope')->assertNotFound();
});

it('shows the Arabic store-unavailable page for unknown and inactive tenants and an Arabic 404 elsewhere', function () {
    ($this->mkTenant)('closed', false);

    foreach (['http://closed.3alraf.test/ar', 'http://ghost.3alraf.test/ar'] as $url) {
        $this->get($url)->assertNotFound()->assertSee('المتجر غير متاح')->assertSee('dir="rtl"', false)->assertSee('http://3alraf.test', false);
    }
    $this->get($this->s.'/category/missing-category')->assertNotFound()->assertSee('الصفحة غير موجودة');
});

// ---------------------------------------------------------------- tenant isolation
it('rejects tenant A credentials on tenant B host (admin and storefront)', function () {
    $sa = ($this->mkTenant)('sa');
    TenantAdmin::withoutGlobalScopes()->create(['tenant_id' => $sa->id, 'name' => 'SA Owner', 'email' => 'owner@sa.test', 'password' => 'password', 'is_active' => true]);
    Customer::withoutGlobalScopes()->create(['tenant_id' => $this->egy->id, 'name' => 'E', 'email' => 'cust@egy.test', 'password' => 'password', 'is_active' => true]);

    $this->postJson('http://sa.3alraf.test/admin/login', ['email' => 'owner@egy.test', 'password' => 'password'])->assertStatus(422);
    $this->postJson('http://sa.3alraf.test/ar/login', ['email' => 'cust@egy.test', 'password' => 'password'])->assertStatus(422);
    $this->postJson('http://sa.3alraf.test/admin/login', ['email' => 'owner@sa.test', 'password' => 'password'])->assertOk();   // positive control
});

it('blocks cross-tenant access to orders, returns, proofs, token routes and reports', function () {
    $sa = ($this->mkTenant)('sa');
    $order = Order::withoutGlobalScopes()->forceCreate(['tenant_id' => $sa->id, 'number' => 'ORD-SA0001', 'public_token' => (string) \Illuminate\Support\Str::uuid(), 'customer_name' => 'Zed', 'customer_phone' => '0100',
        'address' => 'x', 'status' => 'delivered', 'payment_status' => 'unpaid', 'subtotal' => 100, 'total' => 100, 'created_at' => now(), 'updated_at' => now()]);
    $ret = OrderReturn::withoutGlobalScopes()->forceCreate(['tenant_id' => $sa->id, 'order_id' => $order->id, 'number' => 'RET-SA0001', 'status' => 'requested', 'return_value' => 10]);

    $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $this->get("{$this->t}/orders/{$order->id}")->assertNotFound();
    $this->get("{$this->t}/orders/{$order->id}/invoice")->assertNotFound();
    $this->postJson("{$this->t}/orders/{$order->id}/proof/approve")->assertNotFound();
    $this->postJson("{$this->t}/orders/{$order->id}/status", ['status' => 'confirmed'])->assertNotFound();
    $this->get("{$this->t}/returns/{$ret->id}")->assertNotFound();
    $this->postJson("{$this->t}/returns/{$ret->id}/approve")->assertNotFound();
    $this->postJson("{$this->t}/returns/{$ret->id}/refund")->assertNotFound();

    // public token routes resolve inside the current tenant only
    $this->get("{$this->s}/order/{$order->public_token}")->assertNotFound();
    $this->postJson("{$this->s}/order/{$order->public_token}/proof", ['proof' => \Illuminate\Http\UploadedFile::fake()->image('p.jpg')])->assertNotFound();
    $this->get("{$this->s}/payments/return/{$order->public_token}")->assertRedirect();   // only redirects to the order page, which is a 404 here

    // reports never include the other tenant's orders
    $this->get("{$this->t}/reports/sales?from=2000-01-01&to=2100-01-01")->assertOk()->assertDontSee('ORD-SA0001');
});

it('stops a customer from requesting a return on somebody elses order', function () {
    $this->actingAs(Customer::withoutGlobalScopes()->create(['tenant_id' => $this->egy->id, 'name' => 'Mallory', 'email' => 'm@egy.test', 'password' => 'password', 'is_active' => true]), 'web');
    $order = Order::withoutGlobalScopes()->forceCreate(['tenant_id' => $this->egy->id, 'number' => 'ORD-X', 'public_token' => (string) \Illuminate\Support\Str::uuid(), 'customer_name' => 'V', 'customer_phone' => '1', 'address' => 'x',
        'status' => 'delivered', 'payment_status' => 'paid', 'subtotal' => 1, 'total' => 1]);
    $this->postJson("{$this->s}/order/{$order->public_token}/return", ['items' => [['order_item_id' => 1, 'quantity' => 1]], 'reason' => 'x'])->assertStatus(403);
});

// ---------------------------------------------------------------- auth hardening
it('locks an e-mail out after repeated failed logins even when the password is then correct', function () {
    foreach (range(1, 5) as $i) {
        $this->postJson("{$this->t}/login", ['email' => 'owner@egy.test', 'password' => 'wrong'])->assertStatus(422);
    }
    $this->postJson("{$this->t}/login", ['email' => 'owner@egy.test', 'password' => 'password'])->assertStatus(422)->assertJsonValidationErrors('email');
    // a different e-mail is unaffected by that lockout
    $this->postJson("{$this->t}/login", ['email' => 'nobody@egy.test', 'password' => 'x'])->assertStatus(422);
});

it('invalidates the whole session on logout when no other guard remains, keeps it when one does', function () {
    $cust = Customer::withoutGlobalScopes()->create(['tenant_id' => $this->egy->id, 'name' => 'C', 'email' => 'c1@egy.test', 'password' => 'password', 'is_active' => true]);
    $admin = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();

    $this->actingAs($cust, 'web')->withSession(['marker' => 'x'])->post("{$this->s}/logout")->assertRedirect();
    expect(session('marker'))->toBeNull();

    $this->actingAs($cust, 'web')->actingAs($admin, 'tenant')->withSession(['marker' => 'y'])->post("{$this->s}/logout")->assertRedirect();
    expect(session('marker'))->toBe('y');
});

it('throttles login/validate, cart quote and uploads', function () {
    foreach (range(1, 30) as $i) {
        $this->postJson("{$this->t}/login/validate", ['email' => 'a@b.test', 'password' => 'x'])->assertOk();
    }
    $this->postJson("{$this->t}/login/validate", ['email' => 'a@b.test', 'password' => 'x'])->assertStatus(429);

    foreach (range(1, 60) as $i) {
        $this->postJson("{$this->s}/cart/quote", []);
    }
    $this->postJson("{$this->s}/cart/quote", [])->assertStatus(429);
});

// ---------------------------------------------------------------- orders: coupons + stock
function hardeningOrderContext(): void
{
    $t = test();
    app()->instance('currentTenant', $t->egy);
    $t->branch = Branch::first();
    $t->product = Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج'], 'slug' => 'h1', 'sku' => 'H1', 'barcode' => '2000000000015']);
    TenantProduct::create(['product_id' => $t->product->id, 'price' => 100, 'cost' => 60, 'is_active' => true]);
    app(StockLedger::class)->record($t->branch->id, $t->product->id, 0, T::PURCHASE_IN, 10, '60.00');
    $t->place = fn (array $o = [], ?array $lines = null) => app(OrderPlacementService::class)->place(new CheckoutData(...array_merge([
        'name' => 'Ali', 'phone' => '0100', 'cityId' => City::first()->id, 'address' => 'St',
        'shippingCompanyId' => ShippingCompany::first()->id, 'paymentMethodId' => PaymentMethod::where('type', 'cod')->first()->id,
    ], $o)), $lines ?? [['product_id' => $t->product->id, 'variant_id' => 0, 'qty' => 1]])['order'];
}

it('applies the per-customer coupon limit to guests by e-mail and by phone', function () {
    hardeningOrderContext();
    DiscountCode::create(['code' => 'ONCE', 'type' => 'fixed', 'value' => 10, 'scope' => 'all', 'is_active' => true, 'min_order' => 0, 'per_customer_limit' => 1]);

    ($this->place)(['discountCode' => 'once', 'email' => 'Guest@X.test', 'phone' => '0100-111']);    // first use OK (code lookup is case-insensitive)
    $blocked = fn (array $o) => expect(fn () => ($this->place)($o + ['discountCode' => 'ONCE']))->toThrow(ValidationException::class);
    $blocked(['email' => 'guest@x.test', 'phone' => '0999']);          // same e-mail, different case
    $blocked(['email' => 'other@x.test', 'phone' => '0100111']);       // same phone, different formatting
    $fresh = ($this->place)(['discountCode' => 'ONCE', 'email' => 'new@x.test', 'phone' => '0222']);
    expect((string) $fresh->discount)->toBe('10.00');
});

it('enforces one discount usage per order and code at the database level', function () {
    hardeningOrderContext();
    $code = DiscountCode::create(['code' => 'DUP', 'type' => 'fixed', 'value' => 5, 'scope' => 'all', 'is_active' => true, 'min_order' => 0]);
    $order = ($this->place)(['discountCode' => 'DUP']);
    expect(fn () => \App\Models\DiscountCodeUsage::create(['discount_code_id' => $code->id, 'order_id' => $order->id, 'amount' => 5]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('reserves stock rows in a fixed (product, variant) order regardless of cart order', function () {
    hardeningOrderContext();
    $second = Product::create(['type' => 'simple', 'name' => ['ar' => 'ب'], 'slug' => 'h2', 'sku' => 'H2', 'barcode' => '2000000000022']);
    TenantProduct::create(['product_id' => $second->id, 'price' => 50, 'cost' => 30, 'is_active' => true]);
    app(StockLedger::class)->record($this->branch->id, $second->id, 0, T::PURCHASE_IN, 10, '30.00');

    $locked = [];
    DB::listen(function ($q) use (&$locked) {
        if (str_contains($q->sql, 'stock_levels') && str_contains($q->sql, 'for update') && str_starts_with($q->sql, 'select')) {
            $locked[] = (int) $q->bindings[1];
        }
    });
    ($this->place)([], [['product_id' => $second->id, 'variant_id' => 0, 'qty' => 1], ['product_id' => $this->product->id, 'variant_id' => 0, 'qty' => 1]]);

    expect($locked)->toBe([$this->product->id, $second->id]);   // lower id locked first although it was the second cart line
});

it('never oversells the last unit and creates first-ever stock rows safely', function () {
    hardeningOrderContext();
    $ledger = app(StockLedger::class);
    $ledger->reserve($this->branch->id, $this->product->id, 0, 10);
    expect(fn () => $ledger->reserve($this->branch->id, $this->product->id, 0, 1))->toThrow(ValidationException::class);
    expect(StockLevel::where('product_id', $this->product->id)->first()->reserved)->toBe(10);

    // no level row yet (backorder on): insert-ignore + lock must not raise a duplicate-key error, twice in a row
    $this->egy->update(['settings' => ['allow_backorder' => true]]);
    app()->instance('currentTenant', $this->egy->fresh());
    $fresh = Product::create(['type' => 'simple', 'name' => ['ar' => 'ج'], 'slug' => 'h3', 'sku' => 'H3', 'barcode' => '2000000000039']);
    $ledger->reserve($this->branch->id, $fresh->id, 0, 1);
    $ledger->reserve($this->branch->id, $fresh->id, 0, 1);
    expect(StockLevel::where('product_id', $fresh->id)->count())->toBe(1)->and(StockLevel::where('product_id', $fresh->id)->first()->reserved)->toBe(2);
});

it('refuses stock writes against a branch of another tenant', function () {
    hardeningOrderContext();
    $sa = ($this->mkTenant)('sa');
    $foreign = Branch::withoutGlobalScopes()->forceCreate(['tenant_id' => $sa->id, 'name' => 'SA branch', 'is_active' => true]);
    $ledger = app(StockLedger::class);

    expect(fn () => $ledger->record($foreign->id, $this->product->id, 0, T::PURCHASE_IN, 5, '10.00'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $ledger->reserve($foreign->id, $this->product->id, 0, 1))->toThrow(InvalidArgumentException::class);
    expect(StockLevel::withoutGlobalScopes()->where('branch_id', $foreign->id)->count())->toBe(0);
});

it('has the new CHECK constraints and the restrictive orders.branch_id foreign key', function () {
    hardeningOrderContext();
    expect(fn () => DB::table('stock_levels')->where('product_id', $this->product->id)->update(['reserved' => -1]))->toThrow(\Illuminate\Database\QueryException::class);
    $order = ($this->place)();
    expect(fn () => Branch::withoutGlobalScopes()->whereKey($order->branch_id)->forceDelete())->toThrow(\Illuminate\Database\QueryException::class);
});
