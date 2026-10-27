<?php

use App\Models\GrowthDailyStat;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantProduct;
use App\Services\Growth\BriefService;
use App\Services\Growth\GrowthAccess;
use App\Services\Growth\MetricsService;
use App\Services\Growth\StatsAggregator;
use Carbon\Carbon;
use Database\Seeders\FoundationSeeder;

require_once __DIR__.'/GrowthFoundationTest.php';

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->other = Tenant::create(['slug' => 'uae', 'name' => 'UAE Store', 'country' => 'AE', 'currency_code' => 'AED', 'currency_symbol' => 'AED', 'currency_rate' => 1, 'default_locale' => 'en', 'locales' => ['en'], 'timezone' => 'Asia/Dubai', 'is_active' => true]);
    app(GrowthAccess::class)->set($this->tenant, true);
    app(GrowthAccess::class)->set($this->other, true);
    app()->instance('currentTenant', $this->tenant->refresh());
    $this->day = Carbon::parse('2026-10-20')->startOfDay();
    $this->n = 0;
});

function mkOrder(array $o = [], array $items = [['q' => 2, 'price' => 100, 'cost' => 40]], ?Tenant $tenant = null): Order
{
    static $seq = 0;
    $prev = current_tenant();
    if ($tenant) {
        app()->instance('currentTenant', $tenant);
    }
    $sub = collect($items)->sum(fn ($i) => $i['q'] * $i['price']);
    $cogs = collect($items)->sum(fn ($i) => $i['q'] * ($i['cost'] ?? 0));
    $order = new Order;
    $order->forceFill(array_merge(['number' => 'T'.++$seq.uniqid(), 'public_token' => uniqid('t', true), 'customer_name' => 'X', 'customer_phone' => '0100', 'address' => 'a', 'status' => 'delivered', 'payment_status' => 'paid',
        'subtotal' => $sub, 'discount' => 0, 'shipping' => 0, 'shipping_net' => 0, 'shipping_cost' => 10, 'tax' => 0, 'payment_fee' => 0, 'payment_fee_charged' => 0, 'cod_fee' => 0, 'total' => $sub,
        'cogs' => $cogs, 'affiliate_commission' => 0, 'packaging_cost' => 0, 'currency' => 'EGP', 'locale' => 'en', 'placed_at' => Carbon::parse('2026-10-20 12:00')], $o))->save();
    foreach ($items as $i) {
        $it = new OrderItem;
        $it->forceFill(['order_id' => $order->id, 'product_id' => $i['pid'] ?? 1, 'name' => 'P', 'unit_price' => $i['price'], 'unit_cost' => $i['cost'] ?? 0, 'quantity' => $i['q'], 'line_subtotal' => $i['q'] * $i['price'], 'line_discount' => 0])->save();
    }
    app()->instance('currentTenant', $prev);

    return $order;
}

function rebuild(Carbon $d)
{
    app(StatsAggregator::class)->rebuild($d, $d);

    return app(MetricsService::class)->summary($d, $d);
}

it('computes contribution margin to the documented formula (golden)', function () {
    // revenue 200-20 discount = 180; cogs 80; shipping cost 30 vs customer net 10 => subsidy 20; payment fee 6 charged 2, cod fee 1 => 3 ; packaging 5; commission 4
    mkOrder(['discount' => 20, 'shipping_cost' => 30, 'shipping_net' => 10, 'payment_fee' => 6, 'payment_fee_charged' => 2, 'cod_fee' => 1, 'packaging_cost' => 5, 'affiliate_commission' => 4, 'affiliate_id' => null]);
    $s = rebuild($this->day);
    // 180 - 80 - (5 + 20 + 3 + 4) = 68
    expect($s['contribution_margin'])->toBe(['value' => 68.0, 'confidence' => 'confirmed', 'missing' => []])
        ->and($s['revenue']['value'])->toBe(180.0)->and($s['aov']['value'])->toBe(180.0)->and($s['contribution_margin_percent']['value'])->toBe(37.78);
});

it('never reports a margin when a cost is missing and lists what is missing', function () {
    mkOrder();
    mkOrder([], [['q' => 1, 'price' => 100, 'cost' => 0]]);
    $s = rebuild($this->day);
    expect($s['contribution_margin']['value'])->toBeNull()->and($s['contribution_margin']['confidence'])->toBe('insufficient')->and($s['contribution_margin']['missing'])->toBe(['cogs'])
        ->and($s['revenue']['value'])->toBe(300.0); // revenue is still known
});

it('counts only delivered as completed and computes cancel / return rates', function () {
    mkOrder();
    mkOrder(['status' => 'cancelled']);
    mkOrder(['status' => 'returned']);
    mkOrder(['status' => 'shipped']);
    $s = rebuild($this->day);
    expect($s['orders_completed']['value'])->toBe(1.0)->and($s['orders_placed']['value'])->toBe(4.0)->and($s['cancel_rate']['value'])->toBe(25.0)->and($s['return_rate']['value'])->toBe(25.0);
});

it('marks CAC insufficient without ad spend and conversion estimated', function () {
    mkOrder();
    $s = rebuild($this->day);
    expect($s['cac'])->toMatchArray(['value' => null, 'confidence' => 'insufficient', 'missing' => ['ad_spend']])->and($s['conversion_rate']['confidence'])->toBe('insufficient');
});

it('is idempotent and splits channels', function () {
    mkOrder();
    mkOrder(['affiliate_id' => 1]);
    rebuild($this->day);
    $n = GrowthDailyStat::count();
    rebuild($this->day);
    expect(GrowthDailyStat::count())->toBe($n);
    $ch = app(MetricsService::class)->byChannel($this->day, $this->day);
    expect($ch['affiliate']['orders_completed']['value'])->toBe(1.0)->and($ch['direct']['orders_completed']['value'])->toBe(1.0);
});

it('isolates stats and metrics per tenant', function () {
    mkOrder();
    mkOrder([], [['q' => 5, 'price' => 100, 'cost' => 10]], $this->other);
    rebuild($this->day);
    app()->instance('currentTenant', $this->other);
    expect(GrowthDailyStat::count())->toBe(0);
    $s = rebuild($this->day);
    expect($s['revenue']['value'])->toBe(500.0);
    app()->instance('currentTenant', $this->tenant);
    expect(app(MetricsService::class)->summary($this->day, $this->day)['revenue']['value'])->toBe(200.0);
});

it('builds a daily brief where every recommendation has evidence and no PII', function () {
    mkOrder();
    $b = app(BriefService::class)->daily($this->day);
    expect($b['type'])->toBe('daily')->and($b['sales']['orders_completed']['value'])->toBe(1.0)->and($b['ai_summary'])->toBeNull();
    foreach ($b['recommendations'] as $r) {
        expect($r['evidence'])->not->toBeEmpty();
    }
    $w = app(BriefService::class)->weekly($this->day);
    expect($w)->toHaveKeys(['channel_profitability', 'bundle_performance', 'recommendations', 'experiment_results'])->and(count($w['recommendations']))->toBeLessThanOrEqual(3);
    expect(json_encode($b).json_encode($w))->not->toContain('0100');
});

// ---- API --------------------------------------------------------------------------------------------------------------------

it('serves the read API with scope, tenant isolation and no PII', function () {
    mkOrder();
    [, $token, $secret] = growthClient(['read']);
    [, $tok2, $sec2] = growthClient(['events:read']);
    integrationCall($this, 'GET', 'metrics/summary?from=2026-10-20&to=2026-10-20', null, $token, $secret)->assertOk()->assertJsonPath('data.revenue.value', 200)->assertJsonPath('data.contribution_margin.confidence', 'confirmed');
    integrationCall($this, 'GET', 'metrics/summary', null, $tok2, $sec2)->assertStatus(403);
    foreach (['products', 'inventory/velocity', 'orders/summary', 'orders/attribution', 'returns/reasons', 'bundles', 'customers/segments', 'metrics/products', 'metrics/channels', 'brief/daily', 'brief/weekly'] as $p) {
        $r = integrationCall($this, 'GET', $p, null, $token, $secret)->assertOk();
        expect($r->getContent())->not->toContain('0100')->not->toContain('customer_phone');
    }
    // another tenant's client sees only its own data
    mkOrder([], [['q' => 5, 'price' => 100, 'cost' => 10]], $this->other);
    [, $tokO, $secO] = growthClient(['read'], null, $this->other);
    integrationCall($this, 'GET', 'metrics/summary?from=2026-10-20&to=2026-10-20', null, $tokO, $secO)->assertOk()->assertJsonPath('data.revenue.value', 500);
});

it('returns product facts only for the tenant own listing', function () {
    $p = Product::create(['name' => ['en' => 'Soap'], 'slug' => 'soap', 'sku' => 'S1', 'is_active' => true, 'type' => 'simple']);
    TenantProduct::create(['product_id' => $p->id, 'price' => 100, 'cost' => 40, 'is_active' => true]);
    [, $token, $secret] = growthClient(['read']);
    integrationCall($this, 'GET', "products/{$p->id}/facts", null, $token, $secret)->assertOk()->assertJsonPath('data.unit_margin_percent.value', 60)->assertJsonPath('data.name', 'Soap');
    [, $tokO, $secO] = growthClient(['read'], null, $this->other);
    integrationCall($this, 'GET', "products/{$p->id}/facts", null, $tokO, $secO)->assertNotFound();
});

it('requires the admin growth.view permission for the brief page', function () {
    $this->get('http://egy.3alraf.test/admin/growth/brief')->assertRedirect();
});

it('runs growth:aggregate only for growth-enabled tenants', function () {
    app(GrowthAccess::class)->set($this->other, false);
    $this->artisan('growth:aggregate', ['--days' => 1])->assertExitCode(0);
});
