<?php

use App\DTOs\Orders\CheckoutData;
use App\Enums\OrderStatus as S;
use App\Enums\StockMovementType as T;
use App\Models\Branch;
use App\Models\CentralAdmin;
use App\Models\City;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->t = 'http://egy.3alraf.test/admin';
    $this->c = 'http://3alraf.test/admin';
    app()->instance('currentTenant', $this->tenant);
    $branch = Branch::first();
    $p = Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج', 'en' => 'Prod'], 'slug' => 'rp', 'sku' => 'RP', 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
    TenantProduct::create(['product_id' => $p->id, 'price' => 100, 'cost' => 60, 'is_active' => true]);
    app(StockLedger::class)->record($branch->id, $p->id, 0, T::PURCHASE_IN, 100, '60.00');
    $this->product = $p;
    $this->place = fn (int $qty) => app(OrderPlacementService::class)->place(new CheckoutData(
        name: 'Ali', phone: '0100', cityId: City::first()->id, address: 'St', shippingCompanyId: ShippingCompany::first()->id,
        paymentMethodId: PaymentMethod::where('type', 'cod')->first()->id), [['product_id' => $p->id, 'variant_id' => 0, 'qty' => $qty]])['order'];
    $this->deliver = function (Order $o) {
        foreach ([S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED] as $to) {
            app(OrderStatusService::class)->change($o, $to);
        }

        return $o->refresh();
    };
    $this->admin = TenantAdmin::first();
    $this->actingAs($this->admin, 'tenant');
});

function reportData($test, string $url, string $key = 'data'): array
{
    return $test->get($url)->assertOk()->viewData($key);
}

function card(array $data, string $label): string
{
    return collect($data['cards'])->firstWhere('label', __("reports.card.$label"))['value'];
}

it('computes the sales and net profit reports from orders per CALCULATIONS.md', function () {
    $delivered = ($this->deliver)(($this->place)(2));
    $pending = ($this->place)(1);                                   // pending: not a sale yet
    $cancelled = ($this->place)(1);
    app(OrderStatusService::class)->change($cancelled, S::CONFIRMED);
    app(OrderStatusService::class)->change($cancelled, S::CANCELLED);
    $confirmed = ($this->place)(3);
    app(OrderStatusService::class)->change($confirmed, S::CONFIRMED);
    $cat = ExpenseCategory::first();
    Expense::create(['expense_category_id' => $cat->id, 'branch_id' => null, 'amount' => '10.00', 'expense_date' => now()->toDateString()]);

    $sales = reportData($this, "{$this->t}/reports/sales");
    $total = number_format((float) ($delivered->total + $confirmed->total), 2);
    expect(card($sales, 'sales'))->toBe($total)->and(card($sales, 'orders'))->toBe('2')
        ->and(card($sales, 'costs'))->toBe(number_format((float) ($delivered->cogs + $confirmed->cogs), 2))
        ->and(card($sales, 'net_profit'))->toBe(number_format((float) ($delivered->net_profit + $confirmed->refresh()->net_profit), 2));   // profit on every ADMIN-confirmed order (not pending/cancelled)
    expect((string) $pending->refresh()->net_profit)->toBe('0.00')->and((float) $confirmed->net_profit)->toBeGreaterThan(0);

    $np = reportData($this, "{$this->t}/reports/net-profit");
    expect(card($np, 'net_profit'))->toBe(number_format((float) ($delivered->net_profit + $confirmed->net_profit) - 10, 2))
        ->and(card($np, 'expenses'))->toBe('10.00');

    $prod = reportData($this, "{$this->t}/reports/products");
    expect($prod['rows'][0]['qty'])->toBe('5')->and($prod['rows'][0]['revenue'])->toBe('500.00');   // 2 delivered + 3 confirmed

    foreach (\App\Services\Reports\ReportService::KEYS as $k) {
        $this->get("{$this->t}/reports/$k")->assertOk();
        $this->get("{$this->t}/reports/$k/export?from=2020-01-01")->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
    $this->get("{$this->t}/reports")->assertOk();
    $this->get("{$this->t}/reports/nope")->assertNotFound();
    $this->get("{$this->t}/")->assertOk()->assertSee('data-chart', false);
    $csv = $this->get("{$this->t}/reports/sales/export")->getContent();
    expect($csv)->toContain(number_format((float) ($delivered->total + $confirmed->total), 2, '.', ''));
});

it('subtracts returns and shows refunds', function () {
    $o = ($this->deliver)(($this->place)(2));
    $item = $o->items()->first();
    $ret = app(\App\Services\Orders\ReturnService::class);
    $r = $ret->request($o, new \App\DTOs\Orders\ReturnRequestData(quantities: [$item->id => 1], reason: 'x', restock: true, refundShipping: false, returnShippingCost: '5.00'));
    $ret->approve($r);
    $rep = reportData($this, "{$this->t}/reports/returns");
    expect(card($rep, 'returns'))->toBe('1')->and(card($rep, 'return_value'))->toBe('100.00');
    $np = reportData($this, "{$this->t}/reports/net-profit");
    // reversal = return_value(100) - returned_cogs(60) = 40 ; return shipping 5
    expect(card($np, 'net_profit'))->toBe(number_format((float) $o->net_profit - 40 - 5, 2));
    expect(reportData($this, "{$this->t}/reports/products")['rows'][0]['qty'])->toBe('1');
});

it('aggregates across tenants with currency rate normalization and isolates tenants', function () {
    $o = ($this->deliver)(($this->place)(2));
    $sa = Tenant::create(['slug' => 'sa', 'name' => 'Saudi', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 2, 'default_locale' => 'ar', 'locales' => ['ar'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    $row = (array) DB::table('orders')->find($o->id);
    unset($row['id']);
    DB::table('orders')->insert(array_merge($row, ['tenant_id' => $sa->id, 'number' => 'SA-1', 'public_token' => 'sa-token-1']));

    // tenant egy sees only its own order (own currency, no conversion)
    $own = reportData($this, "{$this->t}/reports/sales");
    expect(card($own, 'orders'))->toBe('1')->and(card($own, 'sales'))->toBe(number_format((float) $o->total, 2));

    $this->actingAs(CentralAdmin::first(), 'central');
    $all = reportData($this, "{$this->c}/reports/sales");
    expect(card($all, 'orders'))->toBe('2')->and(card($all, 'sales'))->toBe(number_format((float) $o->total * 3, 2))   // 1x + 2x
        ->and($all['currency'])->toBe(config('tenancy.base_currency'));
    $this->post("{$this->c}/switch-tenant", ['tenant_id' => $sa->id]);
    $one = reportData($this, "{$this->c}/reports/sales");
    expect(card($one, 'orders'))->toBe('1')->and(card($one, 'sales'))->toBe(number_format((float) $o->total * 2, 2));
    $this->post("{$this->c}/switch-tenant", ['tenant_id' => '']);
    $dash = $this->get("{$this->c}/")->assertOk()->viewData('d');
    expect($dash['tenants']['rows'])->toHaveCount(2);
    $this->get("{$this->c}/reports/sales/export")->assertOk();

    // sa admin sees nothing of egy
    $saAdmin = TenantAdmin::withoutGlobalScopes()->create(['tenant_id' => $sa->id, 'name' => 'S', 'email' => 's@sa.test', 'password' => 'password', 'is_active' => true]);
    $saAdmin->syncRoles('tenant-owner');
    app()->forgetInstance('currentTenant');
    $this->actingAs($saAdmin, 'tenant');
    $rep = reportData($this, 'http://sa.3alraf.test/admin/reports/sales');
    expect(card($rep, 'orders'))->toBe('1')->and(card($rep, 'sales'))->toBe(number_format((float) $o->total, 2));
});

it('enforces report permissions', function () {
    $role = Role::create(['name' => 't'.$this->tenant->id.':viewer', 'guard_name' => 'tenant']);
    $role->givePermissionTo('reports.view');
    $u = TenantAdmin::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'V', 'email' => 'v@egy.test', 'password' => 'password', 'is_active' => true]);
    $u->assignRole($role);
    $this->actingAs($u, 'tenant');
    $this->get("{$this->t}/reports/sales")->assertOk();
    $this->get("{$this->t}/reports/sales/export")->assertForbidden();
    $this->get("{$this->t}/roles")->assertForbidden();
});

it('manages roles and staff per tenant without leaking across tenants', function () {
    $this->postJson("{$this->t}/roles/validate", ['name' => ''])->assertStatus(422)->assertJsonValidationErrors(['name']);
    $this->postJson("{$this->t}/roles", ['name' => 'Support', 'permissions' => ['orders.view', 'reports.view', 'bogus.perm']])->assertStatus(422);
    $this->postJson("{$this->t}/roles", ['name' => 'Support', 'permissions' => ['orders.view', 'reports.view']])->assertOk();
    $this->postJson("{$this->t}/roles/validate", ['name' => 'Support'])->assertStatus(422);        // duplicate within tenant
    $role = Role::where('name', 't'.$this->tenant->id.':Support')->first();
    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['orders.view', 'reports.view']);
    $this->get("{$this->t}/roles")->assertOk()->assertSee('Support');
    $this->get("{$this->t}/roles/create")->assertOk();

    $ownerRole = Role::findByName('tenant-owner', 'tenant');
    $this->get("{$this->t}/roles/{$ownerRole->id}/edit")->assertForbidden();                          // system role is read-only
    $this->deleteJson("{$this->t}/roles/{$ownerRole->id}")->assertForbidden();

    $this->postJson("{$this->t}/admins", ['name' => 'Sam', 'email' => 'sam@egy.test', 'password' => 'secret123', 'is_active' => 1, 'roles' => [$role->id]])->assertOk();
    $sam = TenantAdmin::where('email', 'sam@egy.test')->first();
    expect($sam->hasRole($role->name))->toBeTrue();
    $this->deleteJson("{$this->t}/roles/{$role->id}")->assertStatus(422);                              // in use
    $this->get("{$this->t}/admins")->assertOk()->assertSee('sam@egy.test');
    $this->putJson("{$this->t}/admins/{$sam->id}", ['name' => 'Sam2', 'email' => 'sam@egy.test', 'is_active' => 0, 'roles' => []])->assertOk();
    expect($sam->fresh()->name)->toBe('Sam2')->and($sam->fresh()->roles)->toHaveCount(0);
    $this->deleteJson("{$this->t}/admins/{$this->admin->id}")->assertStatus(422);                     // not yourself

    // another tenant cannot see / touch it
    $sa = Tenant::create(['slug' => 'sa', 'name' => 'Saudi', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 2, 'default_locale' => 'ar', 'locales' => ['ar'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    $saAdmin = TenantAdmin::withoutGlobalScopes()->create(['tenant_id' => $sa->id, 'name' => 'S', 'email' => 's@sa.test', 'password' => 'password', 'is_active' => true]);
    $saAdmin->syncRoles('tenant-owner');
    app()->forgetInstance('currentTenant');
    $this->actingAs($saAdmin, 'tenant');
    $this->get('http://sa.3alraf.test/admin/roles')->assertOk()->assertDontSee('Support');
    $this->get("http://sa.3alraf.test/admin/roles/{$role->id}/edit")->assertNotFound();
    $this->deleteJson("http://sa.3alraf.test/admin/roles/{$role->id}")->assertNotFound();
    $this->get("http://sa.3alraf.test/admin/admins/{$sam->id}/edit")->assertNotFound();
    $this->postJson('http://sa.3alraf.test/admin/roles', ['name' => 'Support', 'permissions' => ['orders.view']])->assertOk();   // same name allowed in another tenant

    // central
    $this->actingAs(CentralAdmin::first(), 'central');
    $this->get("{$this->c}/roles")->assertOk()->assertDontSee('Support');
    $this->postJson("{$this->c}/roles", ['name' => 'Editors', 'permissions' => ['products.view']])->assertOk();
    $this->get("{$this->c}/admins")->assertOk();
});
