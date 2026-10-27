<?php

use App\Enums\StockMovementType as T;
use App\Models\Branch;
use App\Models\CeoChallenge;
use App\Models\GrowthApproval;
use App\Models\GrowthExperiment;
use App\Models\ImprovementTask;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Models\TrendCandidate;
use App\Services\Growth\ApprovalService;
use App\Services\Growth\CeoChallengeService;
use App\Services\Growth\GrowthAccess;
use App\Services\Growth\GrowthFeatures;
use App\Services\Growth\IntegrationClientService;
use App\Services\Growth\InventoryDecisionService;
use App\Services\Growth\ReturnIntelService;
use App\Services\Growth\SignatureService;
use App\Services\Growth\TrendRadarService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->other = Tenant::firstOrCreate(['slug' => 'sa'], ['name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    app()->instance('currentTenant', $this->tenant);
    foreach ([$this->tenant, $this->other] as $t) {
        app(GrowthAccess::class)->set($t, true);
        app(GrowthFeatures::class)->enableAll($t);
    }
    app()->instance('currentTenant', $this->tenant->refresh());
    $this->base = 'http://egy.3alraf.test';
    $this->off = function (string $key) {
        $t = current_tenant();
        $s = $t->settings;
        $s['growth']['features'][$key] = false;
        $t->update(['settings' => $s]);
        app()->instance('currentTenant', $t->refresh());
    };
    $this->product = function (string $slug = 'serum', int $stock = 10) {
        $pr = Product::create(['type' => 'simple', 'name' => ['en' => 'Item '.$slug, 'ar' => 'صنف '.$slug], 'description' => ['en' => 'x'], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
        TenantProduct::create(['tenant_id' => $this->tenant->id, 'product_id' => $pr->id, 'price' => '100.00', 'cost' => 50, 'is_active' => true]);
        $stock > 0 && app(StockLedger::class)->record(Branch::first()->id, $pr->id, 0, T::PURCHASE_IN, $stock, 50);

        return $pr;
    };
    $this->sale = function (Product $p, int $qty, string $status = 'delivered', ?\Carbon\Carbon $at = null) {
        static $n = 0;
        $t = current_tenant();
        $o = Order::create(['tenant_id' => $t->id, 'number' => 'CP-'.$t->id.'-'.(++$n), 'public_token' => (string) \Illuminate\Support\Str::uuid(), 'customer_name' => 'X', 'customer_phone' => '01001234567', 'address' => 'a', 'subtotal' => $qty * 100, 'total' => $qty * 100, 'status' => $status, 'currency' => $t->currency_code, 'placed_at' => $at ?? now()->subDays(3), 'cancelled_at' => $status === 'cancelled' ? now()->subDay() : null]);
        $i = OrderItem::create(['tenant_id' => $t->id, 'order_id' => $o->id, 'product_id' => $p->id, 'name' => 'n', 'sku' => $p->sku, 'unit_price' => 100, 'unit_cost' => 50, 'quantity' => $qty, 'line_subtotal' => $qty * 100]);

        return [$o, $i];
    };
    $this->ret = function (Order $o, OrderItem $i, ?string $code) {
        static $m = 0;
        $r = OrderReturn::create(['tenant_id' => $this->tenant->id, 'number' => 'R-'.(++$m).'-'.$o->id, 'order_id' => $o->id, 'status' => 'requested', 'reason_code' => $code, 'return_value' => 100]);
        OrderReturnItem::create(['order_return_id' => $r->id, 'order_item_id' => $i->id, 'quantity' => 1, 'return_value' => 100]);

        return $r;
    };
});

function capClient(array $scopes, ?Tenant $tenant = null): array
{
    $prev = current_tenant();
    app()->instance('currentTenant', $tenant ?? $prev);
    $r = app(IntegrationClientService::class)->create('n8n', $scopes, null, null);
    app()->instance('currentTenant', $prev);

    return $r;
}

function capCall($test, string $method, string $path, ?array $body, array $c)
{
    [, $token, $secret] = $c;
    $raw = $body === null ? '' : json_encode($body);
    $ts = time();
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_X_TIMESTAMP' => (string) $ts, 'HTTP_X_SIGNATURE' => app(SignatureService::class)->sign($secret, $ts, $raw), 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];

    return $test->call($method, 'http://central.3alraf.test/api/integration/v1/'.$path, [], [], [], $server, $raw);
}

it('recommends a reorder, caps it, and approving creates only a DRAFT purchase order', function () {
    $p = ($this->product)('a', 10);
    ($this->sale)($p, 30);
    $row = collect(app(InventoryDecisionService::class)->analyze()['rows'])->firstWhere('product_id', $p->id);
    expect($row['action'])->toBe('reorder')->and($row['scenarios']['reorder']['qty'])->toBeGreaterThan(0)->and($row['impact'])->toBeGreaterThan(0)->and($row['abc'])->toBe('A');

    expect(fn () => app(InventoryDecisionService::class)->proposeReorder($p->id, null, null, 1))->toThrow(\Illuminate\Validation\ValidationException::class); // no supplier
    $sup = Supplier::create(['name' => 'S1', 'is_active' => true]);
    $a = app(InventoryDecisionService::class)->proposeReorder($p->id, Branch::first()->id, $sup->id, 1);
    expect(GrowthApproval::count())->toBe(1)->and(PurchaseOrder::count())->toBe(0);
    expect(fn () => app(InventoryDecisionService::class)->proposeReorder($p->id, null, $sup->id, 1))->toThrow(\Illuminate\Validation\ValidationException::class); // duplicate pending

    app(ApprovalService::class)->approve($a->id, TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->value('id'));
    $po = PurchaseOrder::first();
    expect($po->status->value)->toBe('draft')->and($po->received_at)->toBeNull()->and($po->items()->first()->quantity)->toBe($a->payload['qty']);

    // cash cap blocks the recommendation
    config(['growth.guardrails.reorder_cash_cap' => 10]);
    $row = collect(app(InventoryDecisionService::class)->analyze()['rows'])->firstWhere('product_id', $p->id);
    expect($row['action'])->toBe('wait')->and($row['reasons'])->toContain('cash_cap');
});

it('re-checks the cash cap when the approval is approved', function () {
    $p = ($this->product)('b', 10);
    ($this->sale)($p, 30);
    $sup = Supplier::create(['name' => 'S1', 'is_active' => true]);
    $a = app(InventoryDecisionService::class)->proposeReorder($p->id, Branch::first()->id, $sup->id, 1);
    config(['growth.guardrails.reorder_cash_cap' => 10]);
    expect(fn () => app(ApprovalService::class)->approve($a->id, 1))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(PurchaseOrder::count())->toBe(0)->and(GrowthApproval::first()->status)->toBe('pending');
});

it('promotes slow stock within the margin floor and holds clearance for perishables vs season', function () {
    $p = ($this->product)('slow', 20); // no sales
    $r = collect(app(InventoryDecisionService::class)->analyze()['rows'])->firstWhere('product_id', $p->id);
    expect($r['action'])->toBe('promo')->and($r['scenarios']['promo']['discount_percent'])->toBeLessThanOrEqual(30.0)->and($r['scenarios']['promo']['assumption'])->toHaveKey('uplift')->and($r['cash_tied'])->toBe(1000.0);
    expect(app(InventoryDecisionService::class)->analyze()['summary']['slow_moving_value'])->toBe(1000.0);
    // product category in an upcoming season -> wait (hold)
    $cat = \App\Models\Category::create(['name' => ['en' => 'Gifts', 'ar' => 'هدايا'], 'slug' => 'gifts']);
    $p->categories()->attach($cat->id);
    \App\Models\SeasonalEvent::create(['key' => 'x', 'year' => now()->year, 'name_ar' => 'x', 'name_en' => 'x', 'starts_on' => now()->addDays(20), 'ends_on' => now()->addDays(25), 'category_ids' => [$cat->id], 'lead_time_days' => 10, 'status' => 'planned']);
    $r = collect(app(InventoryDecisionService::class)->analyze()['rows'])->firstWhere('product_id', $p->id);
    expect($r['action'])->toBe('wait')->and($r['reasons'])->toContain('season_hold');
});

it('creates Stock-to-Story tasks once from repeated usage questions on stocked, slow products', function () {
    $p = ($this->product)('story', 10);
    for ($i = 0; $i < 3; $i++) {
        \App\Models\Interaction::create(['channel' => 'whatsapp', 'external_id' => 'e'.$i, 'product_id' => $p->id, 'category' => 'usage', 'text_masked' => 'how to use', 'text_hash' => sha1('u'.$i), 'received_at' => now(), 'status' => 'classified']);
    }
    expect(app(InventoryDecisionService::class)->stockToStory())->toBe(1)->and(app(InventoryDecisionService::class)->stockToStory())->toBe(0);
    expect(ImprovementTask::where('rule', 'stock_to_story')->count())->toBe(1);
});

it('aggregates structured return reasons, creates a fix task, and measures before/after', function () {
    $p = ($this->product)('ret', 10);
    foreach (range(1, 3) as $_) {
        [$o, $i] = ($this->sale)($p, 1);
        ($this->ret)($o, $i, 'color_differs');
    }
    [$o2] = ($this->sale)($p, 1, 'cancelled');
    $svc = app(ReturnIntelService::class);
    $svc->tagCancellation($o2->id, 'delivery_delay', 1);
    $agg = $svc->aggregate();
    expect($agg['by_code']['color_differs']['returns'])->toBe(3)->and($agg['by_code']['delivery_delay']['cancellations'])->toBe(1);
    expect($svc->generateTasks())->toBe(1)->and($svc->generateTasks())->toBe(0);
    $t = ImprovementTask::where('rule', 'return_reason')->first();
    expect($t->topic)->toBe('ret_color_differs')->and((float) $t->before_value)->toBe(3.0);
    $t->update(['created_at' => now()->subDays(10)]);
    $svc->updateTask($t->id, ['status' => 'done'], 1);
    expect($t->fresh()->after_value)->not->toBeNull();
    expect(fn () => $svc->tagCancellation($o2->id, 'bogus', 1))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('accepts reason codes through the API with scope, validation and tenant isolation', function () {
    $p = ($this->product)('api', 5);
    [$o, $i] = ($this->sale)($p, 1);
    $r = ($this->ret)($o, $i, null);
    $c = capClient(['read', 'ingest:write']);
    capCall($this, 'POST', 'returns/reasons', ['kind' => 'return', 'id' => $r->id, 'code' => 'size_unclear'], $c)->assertOk();
    expect($r->fresh()->reason_code)->toBe('size_unclear');
    capCall($this, 'POST', 'returns/reasons', ['kind' => 'return', 'id' => $r->id, 'code' => 'nope'], $c)->assertStatus(422);
    capCall($this, 'POST', 'returns/reasons', ['kind' => 'return', 'id' => $r->id, 'code' => 'quality'], capClient(['read']))->assertStatus(403);
    capCall($this, 'POST', 'returns/reasons', ['kind' => 'return', 'id' => $r->id, 'code' => 'quality'], capClient(['ingest:write'], $this->other))->assertStatus(404);
    capCall($this, 'GET', 'returns/reasons', null, $c)->assertOk()->assertJsonPath('data.aggregate.by_code.size_unclear.returns', 1);
});

it('scores trend candidates with weights and uncertainty, is idempotent per week and Trend-to-Test never purchases', function () {
    $p = ($this->product)('hot', 10);
    ($this->sale)($p, 5, 'delivered', now()->subDays(40)); // previous period
    ($this->sale)($p, 20, 'delivered', now()->subDays(3));
    $svc = app(TrendRadarService::class);
    expect($svc->scan())->toBeGreaterThanOrEqual(1)->and($svc->scan())->toBe(0);
    $c = TrendCandidate::where('product_id', $p->id)->first();
    expect($c->score)->toBeGreaterThan(40)->and($c->uncertainty)->toContain('supplier')->and($c->confidence)->not->toBe('high')->and($c->components)->toHaveKeys(['demand', 'margin', 'supplier', 'audience', 'risk', 'content']);
    $e = $svc->toTest($c->id, 1);
    expect($e->status)->toBe('draft')->and($svc->toTest($c->id, 1)->id)->toBe($e->id)->and(GrowthExperiment::count())->toBe(1)->and(PurchaseOrder::count())->toBe(0)->and($c->fresh()->status)->toBe('testing');
    ($this->off)('experiments');
    $c2 = TrendCandidate::create(['week' => '2000-W01', 'item_key' => 'z', 'name' => 'z', 'source' => 'market', 'score' => 50, 'components' => [], 'evidence' => [], 'uncertainty' => [], 'confidence' => 'low']);
    expect(fn () => $svc->toTest($c2->id, 1))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('generates one CEO challenge per week from rules with evidence and respects the cooldown', function () {
    $p = ($this->product)('ceo', 5);
    foreach (range(1, 4) as $_) {
        ($this->sale)($p, 1, 'cancelled');
    }
    ($this->sale)($p, 1);
    app(\App\Services\Growth\StatsAggregator::class)->rebuild(now()->subDays(30), now());
    $svc = app(CeoChallengeService::class);
    $c = $svc->generate();
    expect($c->rule)->toBe('high_cancel')->and($c->evidence)->not->toBeEmpty()->and($c->review_on->isFuture())->toBeTrue()->and($svc->generate()->id)->toBe($c->id)->and(CeoChallenge::count())->toBe(1);
    CeoChallenge::query()->update(['week' => '2000-W01']); // new week, same rule is cooling down
    expect($svc->generate()->rule)->not->toBe('high_cancel');
    $svc->review($c->id, ['status' => 'reviewed', 'result_note' => 'ok'], 1);
    expect($c->fresh()->status)->toBe('reviewed');
});

it('every N6 surface is gated by its switch (admin 404, API 403, job no-op) and tenants are isolated', function () {
    $p = ($this->product)('gate', 10);
    ($this->sale)($p, 30);
    $owner = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
    $this->actingAs($owner, 'tenant');
    foreach (['inventory', 'returns-intel', 'trends', 'ceo'] as $pg) {
        $this->get("{$this->base}/admin/growth/$pg")->assertOk();
    }
    $c = capClient(['read']);
    foreach (['inventory/recommendations', 'returns/reasons', 'trends/candidates', 'ceo-challenge'] as $path) {
        capCall($this, 'GET', $path, null, $c)->assertOk();
    }
    $this->artisan('growth:operate')->assertSuccessful();
    expect(CeoChallenge::count())->toBe(1);
    // other tenant sees nothing of mine
    app()->instance('currentTenant', $this->other);
    expect(CeoChallenge::count())->toBe(1)->and(CeoChallenge::first()->id)->not->toBe(CeoChallenge::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->first()->id); // own row only
    app()->instance('currentTenant', $this->tenant);
    foreach ([['inventory_engine', 'inventory', 'inventory/recommendations'], ['return_intel', 'returns-intel', 'returns/reasons'], ['trend_radar', 'trends', 'trends/candidates'], ['ceo_challenge', 'ceo', 'ceo-challenge']] as [$key, $pg, $path]) {
        ($this->off)($key);
        $this->actingAs($owner, 'tenant');
        $this->get("{$this->base}/admin/growth/$pg")->assertNotFound();
        capCall($this, 'GET', $path, null, $c)->assertStatus(403)->assertJsonPath('error', 'feature_disabled');
    }
    CeoChallenge::query()->delete();
    TrendCandidate::query()->delete();
    $this->artisan('growth:operate')->assertSuccessful();
    expect(CeoChallenge::count())->toBe(0)->and(TrendCandidate::count())->toBe(0);
});
