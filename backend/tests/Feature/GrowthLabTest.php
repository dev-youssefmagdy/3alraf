<?php

use App\Enums\StockMovementType as T;
use App\Models\Affiliate;
use App\Models\Ambassador;
use App\Models\Branch;
use App\Models\GrowthApproval;
use App\Models\GrowthExperiment;
use App\Models\GrowthExperimentResult;
use App\Models\Order;
use App\Models\Product;
use App\Models\SmallBatchTest;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Models\TrackedLink;
use App\Services\Growth\AmbassadorService;
use App\Services\Growth\ApprovalService;
use App\Services\Growth\ExperimentLabService;
use App\Services\Growth\GrowthAccess;
use App\Services\Growth\GrowthFeatures;
use App\Services\Growth\IntegrationClientService;
use App\Services\Growth\SignatureService;
use App\Services\Growth\SmallBatchService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

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
    $this->product = function (string $slug = 'serum', string $price = '100.00', int $stock = 50, $cost = 50) {
        $pr = Product::create(['type' => 'simple', 'name' => ['en' => 'Item '.$slug, 'ar' => 'صنف '.$slug], 'description' => ['en' => 'x'], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
        TenantProduct::create(['tenant_id' => $this->tenant->id, 'product_id' => $pr->id, 'price' => $price, 'cost' => $cost, 'is_active' => true]);
        app(StockLedger::class)->record(Branch::first()->id, $pr->id, 0, T::PURCHASE_IN, $stock, 50);
        TenantProduct::where('product_id', $pr->id)->update(['cost' => $cost]); // the ledger syncs the average cost: pin it for the test

        return $pr;
    };
    $this->exp = function (Product $p, array $o = []) {
        $e = app(ExperimentLabService::class)->create($o + ['name' => 'Test', 'product_id' => $p->id, 'hypothesis' => 'Daily routine buyers like it', 'audience' => 'women 25-40', 'angle' => 'Simple daily routine', 'budget_cap' => 1000], null);

        return $e;
    };
    $this->order = function (string $status, array $attr, array $o = []) {
        static $n = 0;
        $n++;
        $ord = new Order;
        $ord->forceFill($o + ['number' => 'L5'.$n.uniqid(), 'public_token' => uniqid('t', true), 'customer_name' => 'X', 'customer_phone' => '01000000100', 'address' => 'a', 'status' => $status, 'payment_status' => 'paid', 'subtotal' => 100, 'discount' => 0, 'shipping' => 0, 'shipping_net' => 0, 'shipping_cost' => 0, 'tax' => 0,
            'payment_fee' => 0, 'payment_fee_charged' => 0, 'cod_fee' => 0, 'total' => 100, 'cogs' => 40, 'affiliate_commission' => 0, 'packaging_cost' => 0, 'currency' => 'EGP', 'locale' => 'en', 'placed_at' => now()->subDays(20), 'delivered_at' => $status === 'delivered' ? now()->subDays(20) : null, 'attribution' => $attr])->save();
        (new \App\Models\OrderItem)->forceFill(['order_id' => $ord->id, 'product_id' => 1, 'name' => 'P', 'unit_price' => 100, 'unit_cost' => 40, 'quantity' => 1, 'line_subtotal' => 100, 'line_discount' => 0])->save();

        return $ord;
    };
});

function labCall($test, string $method, string $path, ?array $body, array $c, array $headers = [])
{
    [, $token, $secret] = $c;
    $raw = $body === null ? '' : json_encode($body);
    $ts = time();
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_X_TIMESTAMP' => (string) $ts, 'HTTP_X_SIGNATURE' => app(SignatureService::class)->sign($secret, $ts, $raw), 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
    foreach ($headers as $k => $v) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $k))] = $v;
    }

    return $test->call($method, 'http://central.3alraf.test/api/integration/v1/'.$path, [], [], [], $server, $raw);
}

function labClient(array $scopes, ?Tenant $tenant = null): array
{
    $prev = current_tenant();
    app()->instance('currentTenant', $tenant ?? $prev);
    $r = app(IntegrationClientService::class)->create('n8n', $scopes, null, null);
    app()->instance('currentTenant', $prev);

    return $r;
}

it('guards the product pick by stock, cost and margin, and blocks start until claims are approved', function () {
    $lab = app(ExperimentLabService::class);
    $cheap = ($this->product)('cheap', '100.00', 50, 95); // 5% margin
    $low = ($this->product)('low', '100.00', 2);
    $ok = ($this->product)('ok');
    foreach ([$cheap, $low] as $p) {
        expect(fn () => ($this->exp)($p))->toThrow(ValidationException::class);
    }
    $e = ($this->exp)($ok);
    expect($e->utm_campaign)->toBe('exp'.$e->id)->and($e->status)->toBe('draft');
    expect(fn () => $lab->start($e, null))->toThrow(ValidationException::class); // content not approved
    $e->update(['angle' => '100% organic, cures acne and is clinically proven']);
    expect(fn () => $lab->approveContent($e->fresh(), null))->toThrow(ValidationException::class);
    $e->update(['angle' => 'Simple daily routine']);
    $lab->approveContent($e->fresh(), null);
    expect($lab->start($e->fresh(), null)->status)->toBe('running');
});

it('imports ad results idempotently, mirrors spend, and isolates tenants', function () {
    $e = ($this->exp)(($this->product)());
    $c = labClient(['ingest:write', 'read']);
    $body = ['rows' => [['date' => now()->subDay()->toDateString(), 'spend' => 20, 'impressions' => 1000, 'clicks' => 30, 'creative' => 'v1', 'campaign_id' => 'c9']]];
    labCall($this, 'POST', "experiments/{$e->id}/ad-results", $body, $c, ['Idempotency-Key' => 'a1'])->assertOk()->assertJsonPath('data.total_spend', 20);
    labCall($this, 'POST', "experiments/{$e->id}/ad-results", $body, $c, ['Idempotency-Key' => 'a1'])->assertOk();
    $body['rows'][0]['spend'] = 25; // same day + creative: upsert, not a second row
    labCall($this, 'POST', "experiments/{$e->id}/ad-results", $body, $c, ['Idempotency-Key' => 'a2'])->assertOk()->assertJsonPath('data.total_spend', 25);
    expect(\App\Models\ExperimentAdDay::count())->toBe(1)->and((float) GrowthExperimentResult::where('metric', 'ad_spend')->sum('spend'))->toBe(25.0);
    labCall($this, 'POST', "experiments/{$e->id}/ad-results", ['rows' => [['date' => now()->addDay()->toDateString(), 'spend' => -1]]], $c, ['Idempotency-Key' => 'a3'])->assertStatus(422);
    labCall($this, 'POST', "experiments/{$e->id}/ad-results", $body, labClient(['read']), ['Idempotency-Key' => 'a4'])->assertStatus(403);
    labCall($this, 'POST', "experiments/{$e->id}/ad-results", $body, labClient(['ingest:write'], $this->other), ['Idempotency-Key' => 'a5'])->assertStatus(404);
});

it('gives no recommendation before the minimum sample and labels ROAS as not profit', function () {
    $e = ($this->exp)(($this->product)());
    $lab = app(ExperimentLabService::class);
    $lab->importAds($e, [['date' => now()->subDays(2)->toDateString(), 'spend' => 100, 'impressions' => 5000, 'clicks' => 20]]);
    $m = $lab->metrics($e->fresh());
    expect($m['recommendation']['action'])->toBeNull()->and($m['recommendation']['status'])->toBe('collecting')->and($m['recommendation']['reasons'])->toContain('min_days', 'min_clicks', 'min_orders');
    config(['growth.lab.min_days' => 2, 'growth.lab.min_clicks' => 10, 'growth.lab.min_orders' => 2]);
    $lab->importAds($e, [['date' => now()->subDay()->toDateString(), 'spend' => 100, 'impressions' => 5000, 'clicks' => 20]]);
    ($this->order)('delivered', ['utm_campaign' => $e->utm_campaign]);
    ($this->order)('delivered', ['utm_campaign' => $e->utm_campaign]);
    ($this->order)('cancelled', ['utm_campaign' => $e->utm_campaign]);
    ($this->order)('delivered', ['utm_campaign' => 'other']); // not this experiment
    $m = $lab->metrics($e->fresh());
    // revenue 200, margin 120, spend 200 -> margin after ads -80, ROAS 1.0: revise (not stop)
    expect($m['orders_placed'])->toBe(3)->and($m['revenue'])->toBe(200.0)->and($m['roas'])->toBe(1.0)->and($m['cm_after_ads']['value'])->toBe(-80.0)->and($m['recommendation']['action'])->toBe('revise')->and($m['cancel_rate'])->toBe(33.3);
});

it('flags creative fatigue only with enough impressions in both windows', function () {
    $e = ($this->exp)(($this->product)());
    $lab = app(ExperimentLabService::class);
    $rows = [];
    for ($i = 10; $i >= 1; $i--) {
        $rows[] = ['date' => now()->subDays($i)->toDateString(), 'spend' => 10, 'impressions' => 1000, 'clicks' => $i > 3 ? 30 : 10, 'creative' => 'v1'];
        $rows[] = ['date' => now()->subDays($i)->toDateString(), 'spend' => 1, 'impressions' => 50, 'clicks' => $i > 3 ? 5 : 0, 'creative' => 'tiny']; // too small a sample
    }
    $lab->importAds($e, $rows);
    $f = $lab->fatigue($e);
    expect($f)->toHaveCount(1)->and($f[0]['creative'])->toBe('v1')->and($f[0]['ctr_drop_percent'])->toBeGreaterThan(25)->and($f[0]['advice'])->toBe('new_angle');
});

it('requires an approval, a scale-candidate recommendation and a bounded step for budget increases', function () {
    $e = ($this->exp)(($this->product)());
    $lab = app(ExperimentLabService::class);
    expect(fn () => $lab->requestBudgetIncrease($e, 1200, null, null))->toThrow(ValidationException::class); // not ready
    config(['growth.lab.min_days' => 1, 'growth.lab.min_clicks' => 10, 'growth.lab.min_orders' => 2, 'growth.lab.scale_cm_ratio' => 0.5]);
    $lab->importAds($e, [['date' => now()->subDay()->toDateString(), 'spend' => 20, 'impressions' => 2000, 'clicks' => 50]]);
    for ($i = 0; $i < 4; $i++) {
        ($this->order)('delivered', ['utm_campaign' => $e->utm_campaign]);
    }
    expect($lab->metrics($e->fresh())['recommendation']['action'])->toBe('scale_candidate');
    expect(fn () => $lab->requestBudgetIncrease($e->fresh(), 2000, null, null))->toThrow(ValidationException::class); // over 30% step
    expect(fn () => $lab->requestBudgetIncrease($e->fresh(), 900, null, null))->toThrow(ValidationException::class); // not an increase
    $a = $lab->requestBudgetIncrease($e->fresh(), 1250, 'good', null);
    expect((float) $e->fresh()->budget_cap)->toBe(1000.0); // nothing applied yet
    expect(fn () => $lab->requestBudgetIncrease($e->fresh(), 1200, null, null))->toThrow(ValidationException::class); // one pending at a time
    app(ApprovalService::class)->approve($a->id, TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->value('id'));
    expect((float) $e->fresh()->budget_cap)->toBe(1250.0);
});

it('runs the small-batch lab: terms, consent, dedupe, limited stock, decision gate and approval-only reorder', function () {
    $svc = app(SmallBatchService::class);
    expect(fn () => $svc->create(['name' => 'T', 'mode' => 'interest', 'preorder' => true, 'target_signups' => 2], null))->toThrow(ValidationException::class);
    expect(fn () => $svc->create(['name' => 'T', 'mode' => 'limited_stock', 'target_signups' => 2], null))->toThrow(ValidationException::class);
    $t = $svc->create(['name' => 'Batch', 'mode' => 'limited_stock', 'limited_stock_qty' => 3, 'preorder' => true, 'preorder_terms' => 'Pay on delivery in 3 weeks; full refund if cancelled before shipping.', 'target_signups' => 2], null);
    $this->get("{$this->base}/en/launch/{$t->code}")->assertNotFound(); // draft
    $svc->open($t, null);
    $this->get("{$this->base}/en/launch/{$t->code}")->assertOk()->assertSee('Pay on delivery');
    $post = fn (array $d) => $this->postJson("{$this->base}/en/launch/{$t->code}", $d);
    $post(['contact' => 'a@b.com', 'consent' => 1])->assertStatus(422); // terms not accepted
    $post(['contact' => 'a@b.com', 'preorder_accepted' => 1])->assertStatus(422); // no consent
    $post(['contact' => 'a@b.com', 'preorder_accepted' => 1, 'consent' => 1, 'quantity' => 2])->assertOk();
    $post(['contact' => 'A@B.com', 'preorder_accepted' => 1, 'consent' => 1])->assertOk(); // dedupe
    expect(\App\Models\SmallBatchSignup::count())->toBe(1)->and(\App\Models\SmallBatchSignup::first()->contact_masked)->not->toContain('a@b.com');
    $post(['contact' => '01011112222', 'preorder_accepted' => 1, 'consent' => 1, 'quantity' => 2])->assertStatus(422); // over limited stock
    expect(fn () => $svc->decide($t->fresh(), 'reorder', null, 1))->toThrow(ValidationException::class); // gate: target + min days
    $post(['contact' => '01011112222', 'preorder_accepted' => 1, 'consent' => 1])->assertOk();
    $t->update(['opened_at' => now()->subDays(5)]);
    expect($svc->stats($t->fresh())['gate'])->toBe('ready');
    $a = $svc->decide($t->fresh(), 'reorder', 'go', 1);
    expect($a)->toBeInstanceOf(GrowthApproval::class)->and($t->fresh()->decision)->toBeNull();
    app(ApprovalService::class)->approve($a->id, TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->value('id'));
    expect($t->fresh()->decision)->toBe('reorder');
    // other tenant cannot reach this test
    app()->instance('currentTenant', $this->other);
    expect(SmallBatchTest::count())->toBe(0);
});

it('enrols only approved affiliates, refuses fake-review rewards, and reports valid / pending / excluded orders', function () {
    $svc = app(AmbassadorService::class);
    $pending = Affiliate::create(['name' => 'P', 'email' => 'p@x.com', 'password' => 'password123', 'status' => 'pending']);
    expect(fn () => $svc->enroll($pending->id, ['disclosure' => '#ad', 'terms_accepted' => 1], 1))->toThrow(ValidationException::class);
    $aff = Affiliate::create(['name' => 'Amb', 'email' => 'amb@x.com', 'password' => 'password123', 'status' => 'approved', 'approved_at' => now()]);
    expect(fn () => $svc->enroll($aff->id, ['disclosure' => '#ad', 'terms_accepted' => 1, 'reward_rule' => 'Pay 5 for every 5-star review'], 1))->toThrow(ValidationException::class);
    expect(fn () => $svc->enroll($aff->id, ['disclosure' => '#ad'], 1))->toThrow(ValidationException::class); // terms
    $amb = $svc->enroll($aff->id, ['disclosure' => '#ad', 'terms_accepted' => 1, 'reward_per_order' => 5, 'reward_rule' => 'Per delivered order'], 1);
    $p = ($this->product)();
    $l = $svc->link($amb, $p->id, 'instagram');
    $this->get("{$this->base}/l/{$l->code}")->assertRedirect()->assertRedirectContains('aff='.$aff->uuid);
    ($this->order)('delivered', ['utm_content' => 'tl'.$l->code], ['affiliate_id' => $aff->id, 'delivered_at' => now()->subDays(30)]); // valid
    ($this->order)('delivered', [], ['affiliate_id' => $aff->id, 'delivered_at' => now()->subDay(), 'placed_at' => now()->subDays(2)]); // inside window: pending
    ($this->order)('returned', [], ['affiliate_id' => $aff->id, 'placed_at' => now()->subDays(2)]); // excluded
    ($this->order)('cancelled', [], ['affiliate_id' => $aff->id, 'placed_at' => now()->subDays(2)]); // excluded
    $r = $svc->report($amb, now()->subDays(60), now());
    expect($r['valid_orders'])->toBe(1)->and($r['pending_orders'])->toBe(1)->and($r['excluded_orders'])->toBe(2)->and($r['reward_cost'])->toBe(5.0)->and($r['by_channel']['instagram']['clicks'])->toBe(1)
        ->and($r['margin_after_rewards'])->toBe(55.0)->and($r['disclosure'])->toBe('#ad');
    // admin idea library refuses artificial engagement
    $owner = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
    $this->actingAs($owner, 'tenant');
    $this->postJson("{$this->base}/admin/growth/ambassadors/ideas", ['title' => 'Boost', 'body' => 'Buy likes and fake reviews'])->assertStatus(422);
    $this->postJson("{$this->base}/admin/growth/ambassadors/ideas", ['title' => 'Unboxing', 'body' => 'Film an honest unboxing'])->assertOk();
});

it('gates every N5 surface by its switch (admin 404, API 403, public page and links 404)', function () {
    $e = ($this->exp)(($this->product)());
    $t = app(SmallBatchService::class)->create(['name' => 'B', 'mode' => 'interest', 'target_signups' => 1], null);
    app(SmallBatchService::class)->open($t, null);
    $aff = Affiliate::create(['name' => 'Amb', 'email' => 'amb@x.com', 'password' => 'password123', 'status' => 'approved', 'approved_at' => now()]);
    $amb = app(AmbassadorService::class)->enroll($aff->id, ['disclosure' => '#ad', 'terms_accepted' => 1], 1);
    $p = Product::first();
    $al = app(AmbassadorService::class)->link($amb, $p->id, 'tiktok');
    $el = app(ExperimentLabService::class)->link($e, 'paid');
    $c = labClient(['read', 'ingest:write']);
    $owner = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
    $this->actingAs($owner, 'tenant');
    foreach (['lab', "lab/{$e->id}", 'small-batch', 'ambassadors'] as $pg) {
        $this->get("{$this->base}/admin/growth/$pg")->assertOk();
    }
    $this->get("{$this->base}/en/launch/{$t->code}")->assertOk();
    labCall($this, 'GET', "experiments/{$e->id}/lab", null, $c)->assertOk();
    ($this->off)('experiment_lab');
    $this->actingAs($owner, 'tenant');
    $this->get("{$this->base}/admin/growth/lab")->assertNotFound();
    labCall($this, 'GET', "experiments/{$e->id}/lab", null, $c)->assertStatus(403)->assertJsonPath('error', 'feature_disabled');
    labCall($this, 'POST', "experiments/{$e->id}/ad-results", ['rows' => [['date' => now()->toDateString(), 'spend' => 1]]], $c, ['Idempotency-Key' => 'g1'])->assertStatus(403);
    $this->get("{$this->base}/l/{$el->code}")->assertNotFound();
    $this->get("{$this->base}/l/{$al->code}")->assertRedirect(); // ambassadors still on
    ($this->off)('small_batch_lab');
    $this->actingAs($owner, 'tenant');
    $this->get("{$this->base}/admin/growth/small-batch")->assertNotFound();
    $this->get("{$this->base}/en/launch/{$t->code}")->assertNotFound();
    ($this->off)('ambassadors');
    $this->actingAs($owner, 'tenant');
    $this->get("{$this->base}/admin/growth/ambassadors")->assertNotFound();
    $this->get("{$this->base}/l/{$al->code}")->assertNotFound();
    expect(app(GrowthFeatures::class)->active('experiment_lab'))->toBeFalse();
    ($this->off)('experiments'); // experiment_lab depends on experiments
});
