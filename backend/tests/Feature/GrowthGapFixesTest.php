<?php

use App\Models\Branch;
use App\Models\CentralAdmin;
use App\Models\GrowthActionLog;
use App\Models\GrowthBriefDecision;
use App\Models\GrowthDailyStat;
use App\Models\GrowthEvent;
use App\Models\IntegrationClient;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StorefrontVisit;
use App\Models\Tenant;
use App\Models\TenantProduct;
use App\Services\Growth\AttributionService;
use App\Services\Growth\BriefDecisionService;
use App\Services\Growth\DataQuality;
use App\Services\Growth\GrowthAccess;
use App\Services\Growth\GrowthEventService;
use App\Services\Growth\GrowthFeatures;
use App\Services\Growth\MetricsService;
use App\Services\Growth\StatsAggregator;
use Carbon\Carbon;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;


beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->other = Tenant::create(['slug' => 'uae', 'name' => 'UAE Store', 'country' => 'AE', 'currency_code' => 'AED', 'currency_symbol' => 'AED', 'currency_rate' => 1, 'default_locale' => 'en', 'locales' => ['en'], 'timezone' => 'Asia/Dubai', 'is_active' => true]);
    foreach ([$this->tenant, $this->other] as $t) {
        app(GrowthAccess::class)->set($t, true);
    }
    app()->instance('currentTenant', $this->tenant->refresh());
    $this->base = 'http://egy.3alraf.test';
    $this->day = Carbon::parse('2026-10-20')->startOfDay();
    $this->f = app(GrowthFeatures::class);
});

// ---- #13 per-feature switches ----
it('features default OFF, respect dependencies and the master flag, and are per tenant', function () {
    expect($this->f->active('brief'))->toBeFalse();
    $this->f->update($this->tenant, ['truth_guard' => true]);
    expect($this->f->active('truth_guard', $this->tenant->refresh()))->toBeFalse(); // depends on brand_memory
    $this->f->update($this->tenant, ['brand_memory' => true]);
    expect($this->f->active('truth_guard', $this->tenant->refresh()))->toBeTrue()->and($this->f->active('truth_guard', $this->other))->toBeFalse();
    app(GrowthAccess::class)->set($this->tenant, false);
    expect($this->f->active('brand_memory', $this->tenant->refresh()))->toBeFalse();
    expect(GrowthActionLog::withoutGlobalScopes()->where('action', 'features.updated')->count())->toBe(2);
});

it('OFF feature blocks the admin UI (404) and the integration API (403 feature_disabled); ON works', function () {
    $this->f->update($this->tenant, ['integration_api' => true, 'approvals' => true]);
    [$c, $token, $secret] = growthClient();
    $this->actingAs(ownerOf('egy'), 'tenant');
    $this->get("{$this->base}/admin/growth/approvals")->assertOk();
    $this->get("{$this->base}/admin/growth/experiments")->assertNotFound();
    $this->get("{$this->base}/admin/growth/brief")->assertNotFound();
    integrationCall($this, 'GET', 'ping', null, $token, $secret)->assertOk();
    integrationCall($this, 'GET', 'brief/daily', null, $token, $secret)->assertStatus(403)->assertJsonPath('error', 'feature_disabled');
    integrationCall($this, 'GET', 'approvals/1', null, $token, $secret)->assertStatus(404); // feature on, row missing
    $r = integrationCall($this, 'GET', 'features', null, $token, $secret)->assertOk();
    expect($r->json('data.features.approvals'))->toBeTrue()->and($r->json('data.features.brief'))->toBeFalse();
    $this->f->update($this->tenant, ['integration_api' => false]);
    integrationCall($this, 'GET', 'ping', null, $token, $secret)->assertStatus(403)->assertJsonPath('error', 'feature_disabled');
});

it('feature switches are saved from the settings page (growth.manage) and sidebar items hide', function () {
    $this->actingAs(ownerOf('egy'), 'tenant');
    $this->get("{$this->base}/admin/growth/settings")->assertOk()->assertSee('features[brief]', false);
    $this->putJson("{$this->base}/admin/growth/settings/features", ['features' => ['brief' => true, 'experiments' => false]])->assertOk();
    expect($this->f->active('brief', $this->tenant->refresh()))->toBeTrue()->and($this->f->active('experiments'))->toBeFalse();
    $this->get("{$this->base}/admin/growth/settings")->assertOk()->assertSee('/admin/growth/brief', false)->assertDontSee('/admin/growth/experiments', false);
});

it('events are not emitted and scheduled jobs skip a tenant with the outbox feature off', function () {
    Queue::fake();
    $this->f->update($this->tenant, ['integration_api' => true]);
    growthClient(['read'], 'https://n8n.example.com/hook');
    expect(app(GrowthEventService::class)->emit('order.placed', ['order_id' => 1]))->toBeNull();
    $this->f->update($this->tenant, ['events_outbox' => true]);
    expect(app(GrowthEventService::class)->emit('order.placed', ['order_id' => 1]))->not->toBeNull();
    $this->f->update($this->tenant, ['events_outbox' => false]);
    $this->artisan('growth:dispatch-pending')->expectsOutput('Queued: 0')->assertSuccessful();
});

it('central admin toggles the master flag (audited); tenant owners cannot', function () {
    app(GrowthAccess::class)->set($this->tenant, false);
    $this->actingAs(ownerOf('egy'), 'tenant');
    $this->putJson("{$this->base}/admin/tenants/{$this->tenant->id}/growth", ['growth_enabled' => true])->assertStatus(404);
    $this->actingAs(CentralAdmin::first(), 'central');
    $this->get('http://3alraf.test/admin/tenants/'.$this->tenant->id.'/edit')->assertOk()->assertSee('growth_enabled', false);
    $this->putJson('http://3alraf.test/admin/tenants/'.$this->tenant->id.'/growth', ['growth_enabled' => true])->assertOk();
    expect(app(GrowthAccess::class)->enabled($this->tenant->refresh()))->toBeTrue()
        ->and(GrowthActionLog::withoutGlobalScopes()->where('action', 'growth.enabled')->where('tenant_id', $this->tenant->id)->exists())->toBeTrue();
});

// ---- #3 client edit ----
it('edits client name, scopes and webhook (money scopes and SSRF refused) without touching credentials', function () {
    $this->f->update($this->tenant, ['integration_api' => true]);
    [$c, $token] = growthClient(['read']);
    $hash = $c->token_hash;
    $this->actingAs(ownerOf('egy'), 'tenant');
    $this->putJson("{$this->base}/admin/growth/clients/{$c->id}", ['name' => 'renamed', 'scopes' => ['read', 'events:read'], 'webhook_url' => 'https://n8n.example.com/h'])->assertOk();
    $c->refresh();
    expect($c->name)->toBe('renamed')->and($c->scopes)->toBe(['read', 'events:read'])->and($c->token_hash)->toBe($hash);
    $this->putJson("{$this->base}/admin/growth/clients/{$c->id}", ['name' => 'x', 'scopes' => ['read'], 'webhook_url' => 'http://127.0.0.1/h'])->assertStatus(422);
    $this->putJson("{$this->base}/admin/growth/clients/{$c->id}", ['name' => 'x', 'scopes' => ['stock:write']])->assertStatus(422);
    [$o] = growthClient(['read'], null, $this->other);
    $this->putJson("{$this->base}/admin/growth/clients/{$o->id}", ['name' => 'x', 'scopes' => ['read']])->assertNotFound();
});

// ---- #1 low stock safety net ----
function stockFor(Tenant $t, int $onHand): StockLevel
{
    $p = Product::create(['type' => 'simple', 'name' => ['en' => 'Item', 'ar' => 'صنف'], 'slug' => 'it-'.uniqid(), 'sku' => 'S'.uniqid(), 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
    TenantProduct::create(['tenant_id' => $t->id, 'product_id' => $p->id, 'price' => '50', 'cost' => '20', 'is_active' => true]);

    return StockLevel::create(['tenant_id' => $t->id, 'branch_id' => Branch::withoutGlobalScopes()->where('tenant_id', $t->id)->value('id') ?? Branch::first()->id, 'product_id' => $p->id, 'product_variant_id' => 0, 'on_hand' => $onHand, 'reserved' => 0, 'avg_cost' => 20]);
}

it('scan-low-stock emits stock.low once per product per day (raw updates included) and never for other tenants', function () {
    Queue::fake();
    $this->f->update($this->tenant, ['integration_api' => true, 'events_outbox' => true]);
    growthClient(['read'], 'https://n8n.example.com/hook');
    $s = stockFor($this->tenant, 50);
    \Illuminate\Support\Facades\DB::table('stock_levels')->where('id', $s->id)->update(['on_hand' => 2]); // raw: no model event
    $this->artisan('growth:scan-low-stock')->assertSuccessful();
    $this->artisan('growth:scan-low-stock')->assertSuccessful();
    expect(GrowthEvent::withoutGlobalScopes()->where('type', 'stock.low')->count())->toBe(1);
    // the model-event path for the same product/day does not double fire
    $s->refresh()->update(['on_hand' => 60]);
    $s->update(['on_hand' => 1]);
    expect(GrowthEvent::withoutGlobalScopes()->where('type', 'stock.low')->count())->toBe(1);
    stockFor($this->other, 1);
    $this->artisan('growth:scan-low-stock')->assertSuccessful();
    expect(GrowthEvent::withoutGlobalScopes()->where('type', 'stock.low')->where('tenant_id', $this->other->id)->count())->toBe(0);
});

// ---- #5 visits / conversion, #6 attribution ----
it('conversion is orders / unique sessions (confirmed) once visits exist and estimated before', function () {
    mkOrder();
    mkOrder();
    $before = rebuild($this->day)['conversion_rate'];
    expect($before['confidence'])->not->toBe('confirmed');
    StorefrontVisit::create(['visit_date' => '2026-10-20', 'product_id' => 0, 'channel' => 'direct', 'visits' => 30, 'unique_sessions' => 8]);
    $after = rebuild($this->day)['conversion_rate'];
    expect($after)->toBe(['value' => 25.0, 'confidence' => 'confirmed', 'missing' => []]);
    app()->instance('currentTenant', $this->other);
    expect(StorefrontVisit::count())->toBe(0); // tenant isolation
});

it('storefront traffic middleware counts unique sessions without PII, ignores bots, captures first-touch attribution', function () {
    $this->f->update($this->tenant, ['brief' => true]);
    $url = "{$this->base}/en?utm_source=instagram&utm_medium=social&utm_campaign=eid&fbclid=abc";
    $ua = ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120'];
    $this->call('GET', $url, [], [], [], $ua)->assertOk();
    $this->call('GET', $url, [], [], [], $ua)->assertOk();
    $this->call('GET', "{$this->base}/en", [], [], [], ['HTTP_USER_AGENT' => 'Googlebot/2.1'])->assertOk();
    $row = StorefrontVisit::where('product_id', 0)->first();
    expect($row)->not->toBeNull()->and($row->channel)->toBe('paid')->and($row->visits)->toBe(2)->and($row->unique_sessions)->toBe(2) // two cookie-less requests = two sessions
        ->and(\Illuminate\Support\Facades\Schema::hasColumn('storefront_visits', 'ip'))->toBeFalse();
});

it('normalises channels and stores first-touch attribution on the order', function () {
    expect(AttributionService::normalise(['utm_source' => 'facebook', 'utm_medium' => 'cpc']))->toBe('paid')
        ->and(AttributionService::normalise(['utm_source' => 'tiktok']))->toBe('social')
        ->and(AttributionService::normalise(['utm_source' => 'google']))->toBe('search')
        ->and(AttributionService::normalise(['utm_medium' => 'email']))->toBe('email')
        ->and(AttributionService::normalise(['gclid' => true]))->toBe('paid')
        ->and(AttributionService::normalise(['ref' => 'friend']))->toBe('referral')
        ->and(AttributionService::normalise(['referrer_host' => 'www.google.com']))->toBe('search')
        ->and(AttributionService::normalise([]))->toBe('direct');
    $o = mkOrder(['channel' => 'social', 'attribution' => ['utm_source' => 'tiktok', 'channel' => 'social']]);
    expect($o->fresh()->attribution['utm_source'])->toBe('tiktok');
    rebuild($this->day);
    $ch = app(MetricsService::class)->byChannel($this->day, $this->day);
    expect($ch['social']['orders_completed']['value'])->toBe(1.0);
});

// ---- #7 data quality ----
it('data quality lists missing product / line / shipping costs for the tenant only', function () {
    $s = stockFor($this->tenant, 5);
    TenantProduct::where('product_id', $s->product_id)->update(['cost' => 0]);
    mkOrder(['shipping_net' => 20, 'shipping_cost' => 0], [['q' => 1, 'price' => 100, 'cost' => 0]]);
    $keys = collect(app(DataQuality::class)->issues())->pluck('count', 'key')->all();
    expect($keys)->toMatchArray(['product_cost_missing' => 1, 'order_line_cost_missing' => 1, 'shipping_cost_missing' => 1]);
    app()->instance('currentTenant', $this->other);
    expect(app(DataQuality::class)->issues())->toBe([]);
});

// ---- #8 partial returns netted, #9 bundle allocation ----
it('nets partial returns out of revenue, COGS, units and margin (golden)', function () {
    $o = mkOrder([], [['q' => 4, 'price' => 100, 'cost' => 40]]); // revenue 400, cogs 160, shipping cost 10
    OrderItem::where('order_id', $o->id)->update(['returned_qty' => 1]);
    $s = rebuild($this->day);
    // revenue 300, cogs 120, other 10 => 170
    expect($s['revenue']['value'])->toBe(300.0)->and($s['contribution_margin']['value'])->toBe(170.0);
    $p = GrowthDailyStat::where('scope', 'product')->first();
    expect((int) $p->units)->toBe(3)->and((float) $p->cogs)->toBe(120.0);
});

it('allocates order shipping/fees/packaging to bundle lines by revenue share (golden)', function () {
    $o = mkOrder(['packaging_cost' => 10, 'shipping_cost' => 30, 'shipping_net' => 10], [['q' => 1, 'price' => 100, 'cost' => 40, 'pid' => 1], ['q' => 1, 'price' => 300, 'cost' => 100, 'pid' => 2]]);
    // other costs = 10 packaging + 20 subsidy = 30; the bundle line (100 of 400) takes 25% = 7.5
    OrderItem::where('order_id', $o->id)->where('product_id', 1)->update(['bundle_id' => 5, 'bundle_name' => 'Duo']);
    $b = app(MetricsService::class)->bundles($this->day, $this->day);
    expect($b)->toHaveCount(1)->and($b[0]['line_margin']['value'])->toBe(52.5); // 100 - 40 - 7.5
});

// ---- #10 brief decisions ----
it('persists brief decisions with before/after, dismissed recs drop out, accept can spawn an experiment, tenant isolated', function () {
    $this->f->update($this->tenant, ['brief' => true, 'experiments' => true]);
    $this->actingAs(ownerOf('egy'), 'tenant');
    $ev = [['source' => 'stock_levels', 'metric' => 'days_of_cover', 'value' => 3, 'period' => '30d']];
    $this->postJson("{$this->base}/admin/growth/brief/decision", ['recommendation_key' => 'restock_9', 'kind' => 'inventory_low', 'status' => 'accepted', 'note' => 'ordered', 'create_experiment' => true, 'evidence' => $ev])->assertOk();
    $d = GrowthBriefDecision::first();
    expect($d->status)->toBe('accepted')->and((float) $d->metric_before)->toBe(3.0)->and($d->experiment_id)->not->toBeNull();
    $this->postJson("{$this->base}/admin/growth/brief/decision", ['recommendation_key' => 'restock_9', 'status' => 'done', 'metric_after' => 21])->assertOk();
    expect(GrowthBriefDecision::count())->toBe(1)->and((float) GrowthBriefDecision::first()->metric_after)->toBe(21.0);
    $this->postJson("{$this->base}/admin/growth/brief/decision", ['recommendation_key' => 'x', 'status' => 'bogus'])->assertStatus(422);
    app()->instance('currentTenant', $this->other);
    expect(GrowthBriefDecision::count())->toBe(0);
    app()->instance('currentTenant', $this->tenant);
    app(BriefDecisionService::class)->decide(['recommendation_key' => 'offer_1', 'status' => 'dismissed'], null);
    $brief = app(\App\Services\Growth\BriefService::class);
    $m = new ReflectionMethod($brief, 'rank');
    $m->setAccessible(true);
    $ranked = $m->invoke($brief, [['key' => 'offer_1', 'kind' => 'k', 'params' => [], 'impact' => 3, 'effort' => 2, 'score' => 1.5, 'evidence' => $ev], ['key' => 'restock_9', 'kind' => 'k', 'params' => [], 'impact' => 4, 'effort' => 2, 'score' => 2, 'evidence' => $ev]], 5);
    expect(collect($ranked)->pluck('key')->all())->toBe(['restock_9'])->and($ranked[0]['decision']['status'])->toBe('done');
});
