<?php

use App\Models\GrowthDailyStat;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Growth\BriefService;
use App\Services\Growth\GrowthAccess;
use App\Services\Growth\MetricsService;
use App\Services\Growth\StatsAggregator;
use Carbon\Carbon;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    app(GrowthAccess::class)->set(true);
    app(\App\Services\Growth\GrowthFeatures::class)->enableAll();
    $this->day = Carbon::parse('2026-10-20')->startOfDay();
    $this->n = 0;
});

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

it('builds a daily brief where every recommendation has evidence and no PII', function () {
    mkOrder();
    $b = app(BriefService::class)->daily($this->day);
    expect($b['type'])->toBe('daily')->and($b['sales']['orders_completed']['value'])->toBe(1.0)->and($b['ai_summary'])->toBeNull();
    foreach ($b['recommendations'] as $r) {
        expect($r['evidence'])->not->toBeEmpty();
    }
    $w = app(BriefService::class)->weekly($this->day);
    expect($w)->toHaveKeys(['channel_profitability', 'bundle_performance', 'recommendations', 'experiment_results'])->and(count($w['recommendations']))->toBeLessThanOrEqual(3);
    expect(json_encode($b).json_encode($w))->not->toContain('01000000100');
});

// ---- API --------------------------------------------------------------------------------------------------------------------

it('serves the read API with scope and no PII', function () {
    mkOrder();
    [, $token, $secret] = growthClient(['read']);
    [, $tok2, $sec2] = growthClient(['events:read']);
    integrationCall($this, 'GET', 'metrics/summary?from=2026-10-20&to=2026-10-20', null, $token, $secret)->assertOk()->assertJsonPath('data.revenue.value', 200)->assertJsonPath('data.contribution_margin.confidence', 'confirmed');
    integrationCall($this, 'GET', 'metrics/summary', null, $tok2, $sec2)->assertStatus(403);
    foreach (['products', 'inventory/velocity', 'orders/summary', 'orders/attribution', 'returns/reasons', 'bundles', 'customers/segments', 'metrics/products', 'metrics/channels', 'brief/daily', 'brief/weekly'] as $p) {
        $r = integrationCall($this, 'GET', $p, null, $token, $secret)->assertOk();
        expect($r->getContent())->not->toContain('01000000100')->not->toContain('customer_phone');
    }
});

it('returns product facts for a listed product and 404 for an unknown one', function () {
    $p = Product::create(['name' => ['en' => 'Soap'], 'slug' => 'soap', 'sku' => 'S1', 'is_active' => true, 'type' => 'simple']);
    listProduct($p, ['price' => 100, 'cost' => 40, 'is_active' => true]);
    [, $token, $secret] = growthClient(['read']);
    integrationCall($this, 'GET', "products/{$p->id}/facts", null, $token, $secret)->assertOk()->assertJsonPath('data.unit_margin_percent.value', 60)->assertJsonPath('data.name', 'Soap');
    integrationCall($this, 'GET', 'products/999999/facts', null, $token, $secret)->assertNotFound();
});

it('requires the admin growth.view permission for the brief page', function () {
    $this->get('/admin/growth/brief')->assertRedirect();
});

it('runs growth:aggregate as a no-op while growth is switched off', function () {
    app(GrowthAccess::class)->set(false);
    $this->artisan('growth:aggregate', ['--days' => 1])->assertExitCode(0);
});
