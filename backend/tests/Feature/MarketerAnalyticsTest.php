<?php

// Marketer analytics page: real figures matching the internal API definitions, zero-filled periods, grouping, permissions and both languages.

use App\Enums\StockMovementType as T;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\BarcodeService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    $this->owner = Admin::query()->where('email', 'owner@egy.test')->first();
    $p = Product::create(['type' => 'simple', 'name' => ['en' => 'Rose Set', 'ar' => 'طقم ورد'], 'description' => ['en' => 'x'], 'slug' => 'rose', 'sku' => 'ROSE',
        'barcode' => app(BarcodeService::class)->generateUnique()]);
    listProduct($p, ['price' => '100', 'cost' => '60', 'is_active' => true]);
    app(StockLedger::class)->record(Branch::first()->id, $p->id, 0, T::PURCHASE_IN, 10, '60');
    listProduct($p, ['cost' => '60']);
    $this->product = $p->refresh();
    $this->order = function (int $qty, string $status, string $at) {
        $sub = $qty * 100;
        $o = (new Order)->forceFill(['number' => 'A'.uniqid(), 'public_token' => uniqid('t', true), 'customer_name' => 'X', 'customer_phone' => '01000000100', 'address' => 'a', 'status' => $status,
            'payment_status' => 'paid', 'subtotal' => $sub, 'discount' => 0, 'shipping' => 0, 'shipping_net' => 0, 'shipping_cost' => 0, 'tax' => 0, 'payment_fee' => 0, 'payment_fee_charged' => 0,
            'cod_fee' => 0, 'total' => $sub, 'cogs' => $qty * 60, 'affiliate_commission' => 0, 'packaging_cost' => 0, 'currency' => 'EGP', 'locale' => 'en', 'placed_at' => $at, 'channel' => 'social']);
        $o->save();
        $o->forceFill(['created_at' => $at])->saveQuietly();
        (new OrderItem)->forceFill(['order_id' => $o->id, 'product_id' => $this->product->id, 'product_variant_id' => 0, 'name' => 'n', 'sku' => 'ROSE', 'unit_price' => 100, 'unit_cost' => 60,
            'quantity' => $qty, 'line_subtotal' => $sub, 'line_discount' => 0, 'returned_qty' => 0])->save();
    };
});

it('shows real sales, profit and order figures and leaves cancelled orders out of sales', function () {
    ($this->order)(2, 'delivered', '2026-10-05 12:00:00');
    ($this->order)(1, 'delivered', '2026-10-07 12:00:00');
    ($this->order)(4, 'cancelled', '2026-10-07 13:00:00');

    $r = $this->actingAs($this->owner, 'admin')->get('/marketer/analytics?from=2026-10-05&to=2026-10-08')->assertOk();
    $r->assertViewHas('o', fn ($o) => (float) $o['sales']['gross_sales'] === 300.0 && $o['sales']['orders'] === 2 && (float) $o['profit']['gross_profit'] === 120.0 && (float) $o['profit']['gross_margin_percent'] === 40.0);
    $r->assertViewHas('orders', fn ($o) => $o['cancellations']['total'] === 1 && (float) $o['cancellations']['rate_percent'] === 33.33);
    $r->assertViewHas('statuses', fn ($s) => count($s) === 2);
    $r->assertSee('طقم ورد')->assertSee('300.00')->assertSee('سوشيال ميديا')->assertSee('data-chart-card="sales"', false)
        ->assertSee('<select id="group_by"', false)->assertDontSee('&lt;select', false);
    $r->assertViewHas('candidates', fn ($c) => count($c) === 1 && $c[0]['sku'] === 'ROSE')->assertSee('الدرجة');
    $r->assertViewHas('ordersChart', fn ($c) => $c['integer'] === true);
});

it('fills days without orders with zero so the trend is not misleading', function () {
    ($this->order)(1, 'delivered', '2026-10-05 12:00:00');
    ($this->order)(2, 'delivered', '2026-10-08 12:00:00');

    $this->actingAs($this->owner, 'admin')->get('/marketer/analytics?from=2026-10-05&to=2026-10-08&group_by=day')->assertOk()
        ->assertViewHas('salesChart', fn ($c) => $c['labels'] === ['2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08'] && $c['datasets'][0]['data'] === [100.0, 0.0, 0.0, 200.0])
        ->assertViewHas('ordersChart', fn ($c) => $c['datasets'][0]['data'] === [1, 0, 0, 1]);
});

it('groups by month on request, ignores an unknown grouping, and picks a sensible grouping by range length', function () {
    ($this->order)(1, 'delivered', '2026-09-30 12:00:00');
    ($this->order)(3, 'delivered', '2026-10-01 12:00:00');
    $this->actingAs($this->owner, 'admin');

    $this->get('/marketer/analytics?from=2026-09-01&to=2026-10-31&group_by=month')->assertOk()
        ->assertViewHas('salesChart', fn ($c) => $c['labels'] === ['2026-09', '2026-10'] && $c['datasets'][0]['data'] === [100.0, 300.0]);
    $this->get('/marketer/analytics?from=2026-10-01&to=2026-10-03&group_by=bogus')->assertOk()->assertViewHas('groupBy', 'day');
    $this->get('/marketer/analytics?from=2026-05-01&to=2026-08-01')->assertOk()->assertViewHas('groupBy', 'week');
    $this->get('/marketer/analytics?from=2026-01-01&to=2026-10-01')->assertOk()->assertViewHas('groupBy', 'month');
});

it('renders the empty state in Arabic and English without errors', function () {
    $this->actingAs($this->owner, 'admin');
    $this->get('/marketer/analytics')->assertOk()->assertSee('التحليلات')->assertDontSee('marketing.an.');
    $this->withSession(['admin_locale' => 'en'])->get('/marketer/analytics')->assertOk()->assertSee('Sales over time')->assertDontSee('marketing.an.');
});

it('requires marketing.analytics and shows the menu item only to those who hold it', function () {
    $make = function (array $perms, string $email) {
        $a = Admin::create(['name' => 'S', 'email' => $email, 'password' => 'password', 'is_active' => true]);
        $a->givePermissionTo($perms);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $a->fresh();
    };
    $this->get('/marketer/analytics')->assertRedirect();

    $viewer = $make(['marketing.view'], 'v@egy.test');
    $this->actingAs($viewer, 'admin')->get('/marketer/analytics')->assertForbidden();
    $this->get('/marketer')->assertOk()->assertDontSee(route('marketer.analytics.index'), false);

    $analyst = $make(['marketing.view', 'marketing.analytics'], 'a@egy.test');
    $this->actingAs($analyst, 'admin')->get('/marketer/analytics')->assertOk();
    $this->get('/marketer')->assertSee(route('marketer.analytics.index'), false);
});
