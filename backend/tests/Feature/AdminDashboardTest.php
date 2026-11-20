<?php

use App\DTOs\Orders\CheckoutData;
use App\DTOs\Reports\ReportFilter;
use App\Enums\StockMovementType as T;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\City;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Services\BarcodeService;
use App\Services\Orders\OrderPlacementService;
use App\Services\Reports\ReportService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    codUpfront(false);
    $branch = Branch::first();
    $mk = function (string $sku, int $qty, ?int $alert = null) use ($branch) {
        $p = Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج '.$sku, 'en' => 'Prod '.$sku], 'slug' => strtolower($sku), 'sku' => $sku, 'barcode' => app(BarcodeService::class)->generateUnique(), 'min_stock_alert_qty' => $alert]);
        listProduct($p, ['price' => 100, 'cost' => 60, 'is_active' => true]);
        app(StockLedger::class)->record($branch->id, $p->id, 0, T::PURCHASE_IN, $qty, '60.00');

        return $p;
    };
    $this->low = $mk('LOWP', 2, 5);
    $this->ok = $mk('OKP', 100, 5);
    $this->place = fn () => app(OrderPlacementService::class)->place(new CheckoutData(
        name: 'Ali', phone: '01000000100', cityId: City::first()->id, address: 'St', shippingCompanyId: ShippingCompany::first()->id,
        paymentMethodId: PaymentMethod::where('type', 'cod')->first()->id), [['product_id' => $this->ok->id, 'variant_id' => 0, 'qty' => 1]])['order'];
});

it('supplies recent orders and low-stock items to the dashboard data', function () {
    $order = ($this->place)();
    $d = app(ReportService::class)->dashboard(new ReportFilter(now()->subDays(29)->toDateString(), now()->toDateString()));

    expect($d['recentOrders'])->toHaveCount(1)
        ->and($d['recentOrders'][0])->toMatchArray(['number' => $order->number, 'customer' => 'Ali', 'status' => 'pending', 'url' => route('admin.orders.show', $order->id)])
        ->and($d['recentOrders'][0])->toHaveKeys(['total', 'status_label', 'status_class', 'created_at']);
    expect(collect($d['lowStockItems'])->pluck('sku')->all())->toBe(['LOWP'])
        ->and($d['lowStockItems'][0])->toMatchArray(['stock' => 2, 'threshold' => 5]);
});

it('limits recent orders to the latest eight', function () {
    foreach (range(1, 9) as $i) {
        ($this->place)();
    }
    $rows = app(ReportService::class)->recentOrders();
    expect($rows)->toHaveCount(8)->and($rows[0]['id'])->toBeGreaterThan($rows[7]['id']);
});

it('renders the admin dashboard for a reports.view admin', function () {
    $role = Role::create(['name' => 'viewer', 'guard_name' => 'admin']);
    $role->givePermissionTo('reports.view');
    $u = Admin::query()->create(['name' => 'V', 'email' => 'v@egy.test', 'password' => 'password', 'is_active' => true]);
    $u->assignRole($role);
    ($this->place)();
    $this->actingAs($u, 'admin')->get('/admin')->assertOk()
        ->assertViewHas('d', fn ($d) => isset($d['recentOrders'], $d['lowStockItems']) && count($d['lowStockItems']) === 1);
});

it('hides recent-order customer data and low stock from a reports.view-only admin', function () {
    $role = Role::create(['name' => 'reports-only', 'guard_name' => 'admin']);
    $role->givePermissionTo('reports.view');
    $u = Admin::query()->create(['name' => 'R', 'email' => 'r@egy.test', 'password' => 'password', 'is_active' => true]);
    $u->assignRole($role);
    ($this->place)();

    $this->actingAs($u, 'admin')->get('/admin')->assertOk()
        ->assertDontSee('data-dash-recent-orders', false)
        ->assertDontSee('data-dash-low-stock', false)
        ->assertDontSee('Ali')
        ->assertDontSee('LOWP');
});

it('shows recent orders to an orders.view admin and low stock to a products.view admin', function () {
    $role = Role::create(['name' => 'orders-products', 'guard_name' => 'admin']);
    $role->givePermissionTo(['reports.view', 'orders.view', 'products.view']);
    $u = Admin::query()->create(['name' => 'O', 'email' => 'o@egy.test', 'password' => 'password', 'is_active' => true]);
    $u->assignRole($role);
    $order = ($this->place)();

    $this->actingAs($u, 'admin')->get('/admin')->assertOk()
        ->assertSee('data-dash-recent-orders', false)
        ->assertSee('data-dash-low-stock', false)
        ->assertSee('Ali')
        ->assertSee(route('admin.orders.show', $order->id), false)
        ->assertSee('LOWP');
});

it('shows only recent orders to an orders.view admin without stock permissions', function () {
    $role = Role::create(['name' => 'orders-only', 'guard_name' => 'admin']);
    $role->givePermissionTo(['reports.view', 'orders.view']);
    $u = Admin::query()->create(['name' => 'O2', 'email' => 'o2@egy.test', 'password' => 'password', 'is_active' => true]);
    $u->assignRole($role);
    ($this->place)();

    $this->actingAs($u, 'admin')->get('/admin')->assertOk()
        ->assertSee('data-dash-recent-orders', false)
        ->assertDontSee('data-dash-low-stock', false)
        ->assertDontSee('LOWP');
});
