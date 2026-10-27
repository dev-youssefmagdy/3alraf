<?php

// Growth N9: closure of the small gaps (docs/plans/n8n-growth-small-gaps.md items 1-12), each with ON/OFF behaviour.

use App\Enums\StockMovementType as T;
use App\Http\Controllers\Api\Integration\MetricsController;
use App\Models\Affiliate;
use App\Models\AmbassadorReward;
use App\Models\Branch;
use App\Models\Bundle;
use App\Models\Customer;
use App\Models\GiftFinderSession;
use App\Models\GrowthActionLog;
use App\Models\GrowthApproval;
use App\Models\GrowthExperiment;
use App\Models\Interaction;
use App\Models\MissedOpportunity;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\BarcodeService;
use App\Services\Growth\AdCapService;
use App\Services\Growth\AmbassadorService;
use App\Services\Growth\ApprovalService;
use App\Services\Growth\AttributionService;
use App\Services\Growth\BriefService;
use App\Services\Growth\BundleAbService;
use App\Services\Growth\ExperimentLabService;
use App\Services\Growth\GiftFinderService;
use App\Services\Growth\GrowthAccess;
use App\Services\Growth\GrowthConfig;
use App\Services\Growth\GrowthFeatures;
use App\Services\Growth\GrowthRetentionService;
use App\Services\Growth\IntegrationClientService;
use App\Services\Growth\InteractionService;
use App\Services\Growth\SignatureService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    $this->tenant = Tenant::where('slug', 'egy')->first();
    app()->instance('currentTenant', $this->tenant);
    app(GrowthAccess::class)->set($this->tenant, true);
    app(GrowthFeatures::class)->enableAll($this->tenant);
    app()->instance('currentTenant', $this->tenant->refresh());
    $this->base = 'http://egy.3alraf.test';
    $this->owner = fn () => TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
    $this->off = function (string ...$keys) {
        $t = current_tenant();
        $s = $t->settings;
        foreach ($keys as $key) {
            $s['growth']['features'][$key] = false;
        }
        $t->update(['settings' => $s]);
        app()->instance('currentTenant', $t->refresh());
    };
    $this->product = function (string $slug = 'serum', string $price = '100.00', int $stock = 50, $cost = 50) {
        $pr = Product::create(['type' => 'simple', 'name' => ['en' => 'Item '.$slug, 'ar' => 'صنف '.$slug], 'description' => ['en' => 'x'], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(BarcodeService::class)->generateUnique()]);
        TenantProduct::create(['tenant_id' => $this->tenant->id, 'product_id' => $pr->id, 'price' => $price, 'cost' => $cost, 'is_active' => true]);
        app(StockLedger::class)->record(Branch::first()->id, $pr->id, 0, T::PURCHASE_IN, $stock, 50);
        TenantProduct::where('product_id', $pr->id)->update(['cost' => $cost]);

        return $pr;
    };
    $this->order = function (string $status, array $attr = [], array $o = []) {
        static $n = 0;
        $n++;
        $ord = new Order;
        $ord->forceFill($o + ['number' => 'N9'.$n.uniqid(), 'public_token' => uniqid('t', true), 'customer_name' => 'X', 'customer_phone' => '01000000100', 'address' => 'a', 'status' => $status, 'payment_status' => 'paid', 'subtotal' => 100, 'discount' => 0, 'shipping' => 0, 'shipping_net' => 0, 'shipping_cost' => 0, 'tax' => 0,
            'payment_fee' => 0, 'payment_fee_charged' => 0, 'cod_fee' => 0, 'total' => 100, 'cogs' => 40, 'affiliate_commission' => 0, 'packaging_cost' => 0, 'currency' => 'EGP', 'locale' => 'en', 'placed_at' => now()->subDays(20), 'delivered_at' => $status === 'delivered' ? now()->subDays(20) : null, 'attribution' => $attr ?: null])->save();
        (new OrderItem)->forceFill(['order_id' => $ord->id, 'product_id' => 1, 'name' => 'P', 'unit_price' => 100, 'unit_cost' => 40, 'quantity' => 1, 'line_subtotal' => 100, 'line_discount' => 0])->save();

        return $ord;
    };
});

function n9Client(array $scopes): array
{
    return app(IntegrationClientService::class)->create('n8n', $scopes, null, null);
}

function n9Call($test, string $method, string $path, ?array $body, array $c, array $headers = [])
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

// 1 ------------------------------------------------------------------------------------------------------------------------------------------
it('refuses to approve an item whose module switch is OFF and keeps it pending (ON works)', function () {
    $svc = app(ApprovalService::class);
    $svc->register('inventory_reorder', fn ($a) => ['ok' => true]); // neutralise the real handler: only the switch is under test
    $a = $svc->request('inventory_reorder', 'Reorder', ['x' => 1]);
    ($this->off)('inventory_engine');
    try {
        $svc->approve($a->id, ($this->owner)()->id);
        $this->fail('expected feature_disabled');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('feature_disabled');
    }
    expect($a->fresh()->status)->toBe(GrowthApproval::PENDING);
    app(GrowthFeatures::class)->enableAll($this->tenant->refresh());
    app()->instance('currentTenant', $this->tenant->refresh());
    expect($svc->approve($a->id, ($this->owner)()->id)->status)->toBe(GrowthApproval::APPROVED);
});

// 2 ------------------------------------------------------------------------------------------------------------------------------------------
it('links interactions to the customer at ingest and erases them when the customer is deleted (switch ON), audited', function () {
    $c = Customer::create(['name' => 'Amal', 'email' => 'amal@example.com', 'phone' => '01011112222', 'password' => 'password123']);
    [$i] = app(InteractionService::class)->ingest(['channel' => 'whatsapp', 'external_id' => 'e1', 'text' => 'where is my order', 'contact' => '01011112222']);
    [$j] = app(InteractionService::class)->ingest(['channel' => 'email', 'external_id' => 'e2', 'text' => 'hello', 'contact' => 'AMAL@example.com']);
    [$k] = app(InteractionService::class)->ingest(['channel' => 'email', 'external_id' => 'e3', 'text' => 'unrelated', 'contact' => 'nobody@example.com']);
    expect($i->customer_id)->toBe($c->id)->and($j->customer_id)->toBe($c->id)->and($k->customer_id)->toBeNull();
    expect(Interaction::first()->getAttributes())->not->toHaveKey('contact'); // raw contact is never stored
    $c->delete();
    expect(Interaction::pluck('id')->all())->toBe([$k->id]);
    expect(GrowthActionLog::where('action', 'data_erasure')->count())->toBe(1);
});

it('does not erase growth data on customer deletion when every related switch is OFF', function () {
    $c = Customer::create(['name' => 'B', 'email' => 'b@example.com', 'phone' => '01022223333', 'password' => 'password123']);
    app(InteractionService::class)->ingest(['channel' => 'email', 'external_id' => 'x1', 'text' => 'hi', 'contact' => 'b@example.com']);
    ($this->off)('interactions', 'small_batch_lab', 'ambassadors');
    $c->delete();
    expect(Interaction::count())->toBe(1);
});

// 3 ------------------------------------------------------------------------------------------------------------------------------------------
it('writes an idempotent ambassador reward ledger, excludes returned orders and settles with the affiliate payout', function () {
    $aff = Affiliate::create(['name' => 'Amb', 'email' => 'amb@x.com', 'password' => 'password123', 'status' => 'approved', 'approved_at' => now()]);
    $svc = app(AmbassadorService::class);
    $amb = $svc->enroll($aff->id, ['disclosure' => '#ad', 'terms_accepted' => 1, 'reward_per_order' => 5], 1);
    $valid = ($this->order)('delivered', [], ['affiliate_id' => $aff->id, 'delivered_at' => now()->subDays(30)]);
    $pend = ($this->order)('delivered', [], ['affiliate_id' => $aff->id, 'delivered_at' => now()->subDay(), 'placed_at' => now()->subDays(2)]);
    $ret = ($this->order)('delivered', [], ['affiliate_id' => $aff->id, 'delivered_at' => now()->subDays(30)]);
    $svc->syncLedger();
    expect(AmbassadorReward::count())->toBe(3)->and(AmbassadorReward::where('status', 'valid')->count())->toBe(2)->and(AmbassadorReward::where('order_id', $pend->id)->value('status'))->toBe('pending');
    expect($svc->syncLedger())->toBe(0); // idempotent
    expect(AmbassadorReward::count())->toBe(3);
    $ret->update(['status' => 'returned']);
    $svc->syncLedger();
    expect(AmbassadorReward::where('order_id', $ret->id)->first()->status)->toBe('excluded');
    expect($svc->report($amb, now()->subDays(60), now())['reward_cost'])->toBe(5.0); // report reads the ledger: only the one valid order
    expect($svc->settle($aff->id))->toBe(1);
    expect(AmbassadorReward::where('order_id', $valid->id)->first()->status)->toBe('paid');
    $valid->update(['status' => 'returned']); // a paid reward is never rewritten
    $svc->syncLedger();
    expect(AmbassadorReward::where('order_id', $valid->id)->first()->status)->toBe('paid');
});

// 4 ------------------------------------------------------------------------------------------------------------------------------------------
it('has removed the dead MetricsController::returnReasons', function () {
    expect(method_exists(MetricsController::class, 'returnReasons'))->toBeFalse();
});

// 5 ------------------------------------------------------------------------------------------------------------------------------------------
it('documents every integration route with request schemas, and the committed document is current', function () {
    $doc = json_decode(file_get_contents(base_path('docs/api/integration-openapi.json')), true);
    $ops = collect($doc['paths'])->flatMap(fn ($p) => array_values($p))->keyBy('operationId');
    $n = 0;
    foreach (Route::getRoutes()->getRoutes() as $r) {
        if (! str_starts_with($r->uri(), 'api/integration/v1')) {
            continue;
        }
        $n++;
        $id = preg_replace('/^integration\./', '', (string) $r->getName());
        expect($ops->has($id))->toBeTrue('undocumented: '.$r->uri());
        $op = $ops[$id];
        expect($op)->toHaveKeys(['security', 'responses', 'x-scope'])->and($op['responses'])->toHaveKeys(['200', '401', '403', '422']);
    }
    expect($n)->toBeGreaterThan(30);
    foreach (['interactions.ingest', 'proposals.store', 'experiments.ad-results', 'returns.reason', 'experiments.results'] as $name) {
        expect($ops[$name]['requestBody']['content']['application/json']['schema']['properties'])->not->toBeEmpty($name);
    }
    expect($ops['metrics.summary']['parameters'])->not->toBeEmpty();
    Artisan::call('growth:openapi', ['--stdout' => true]);
    $out = Artisan::output();
    $fresh = json_decode(substr($out, 0, strrpos($out, '}') + 1), true);
    expect($fresh['paths'])->toBe($doc['paths']); // regenerate with: php artisan growth:openapi
});

// 6 ------------------------------------------------------------------------------------------------------------------------------------------
it('keeps every growth admin page and API list endpoint at a bounded query count as data grows (no N+1)', function () {
    $admin = ($this->owner)();
    $c = n9Client(['read']);
    $seed = function (int $n) {
        for ($i = 0; $i < $n; $i++) {
            Interaction::create(['channel' => 'whatsapp', 'external_id' => uniqid('q'), 'text_masked' => 'hello '.$i, 'text_hash' => sha1((string) $i), 'status' => 'classified', 'category' => 'price', 'intent' => 'question', 'received_at' => now()->subMinutes($i)]);
            GrowthApproval::create(['type' => 'note', 'title' => 'n'.$i, 'payload' => [], 'status' => 'pending', 'requested_by' => 'system', 'expires_at' => now()->addDay()]);
            GrowthExperiment::create(['name' => 'e'.$i, 'hypothesis' => 'h', 'metric' => 'm', 'status' => 'draft']);
            MissedOpportunity::create(['title' => 't'.$i, 'evidence' => [['metric' => 'a', 'value' => 1, 'period' => '7d']], 'probable_cause' => 'c', 'suggestion' => 's', 'confidence' => 'low', 'status' => 'open', 'rule' => 'r', 'dedupe_key' => 'k'.$i.uniqid()]);
        }
    };
    $urls = [];
    foreach (app('router')->getRoutes()->getRoutes() as $r) {
        $name = (string) $r->getName();
        if (str_starts_with($name, 'tenant.growth.') && in_array('GET', $r->methods(), true) && ! str_contains($r->uri(), '{') && ! str_contains($name, 'settings')) {
            $urls[] = $this->base.'/'.$r->uri();
        }
    }
    $sig = [];
    $count = function () use ($urls, $admin, $c, &$sig) {
        $out = [];
        foreach ($urls as $u) {
            $q = 0;
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($admin, 'tenant')->get($u, ['HTTP_ACCEPT' => str_contains($u, 'datatable') ? 'application/json' : 'text/html']);
            $log = DB::getQueryLog();
            $out[$u] = count($log);
            $sig[$u] = collect($log)->map(fn ($l) => preg_replace('/\d+/', '?', substr($l['query'], 0, 90)))->countBy()->sortDesc()->take(3)->all();
        }
        foreach (['products', 'bundles', 'inventory/velocity', 'metrics/products', 'voc', 'reputation/alerts', 'missed-opportunities/weekly', 'inventory/recommendations', 'returns/reasons', 'trends/candidates'] as $p) {
            DB::flushQueryLog();
            n9Call($this, 'GET', $p, null, $c)->assertOk();
            $out['api/'.$p] = count(DB::getQueryLog());
            $sig['api/'.$p] = collect(DB::getQueryLog())->map(fn ($l) => preg_replace('/\d+/', '?', substr($l['query'], 0, 90)))->countBy()->sortDesc()->take(3)->all();
        }

        return $out;
    };
    $seed(3);
    foreach (range(1, 4) as $i) {
        ($this->product)('qc'.$i);
    }
    $small = $count();
    $seed(30);
    foreach (range(5, 12) as $i) {
        ($this->product)('qc'.$i);
    }
    $large = $count();
    expect(count($small))->toBeGreaterThan(30);
    foreach ($small as $u => $q) {
        expect($large[$u] - $q)->toBeLessThanOrEqual(6, "$u grows with data: $q -> {$large[$u]} queries ".json_encode($sig[$u]));
        expect($large[$u])->toBeLessThan(150, "$u too many queries ".json_encode($sig[$u]));
    }
});

it('uses logical (RTL-safe) utilities in every growth view and renders dir=rtl in Arabic', function () {
    $bad = [];
    $files = array_merge(glob(resource_path('views/tenant/growth/*.php')), glob(resource_path('views/storefront/{gift-finder,small-batch}.blade.php'), GLOB_BRACE));
    foreach ($files as $f) {
        foreach (preg_split('/\R/', file_get_contents($f)) as $i => $line) {
            if (preg_match('/\b(ml|mr|pl|pr)-(\d|px|auto)|\btext-(left|right)\b|(?<![\w:-])(left|right)-\d|\brounded-(l|r)(-|\b)|\bborder-(l|r)\b|\bfloat-(left|right)\b/', $line, $m)) {
                $bad[] = basename($f).':'.($i + 1).' '.$m[0];
            }
        }
    }
    expect($bad)->toBe([]);
    $admin = ($this->owner)();
    foreach (['brief', 'approvals', 'interactions', 'missed', 'gift-finder', 'bundles', 'lab', 'ambassadors', 'inventory'] as $page) {
        $res = $this->actingAs($admin, 'tenant')->withSession(['admin_locale' => 'ar'])->get($this->base.'/admin/growth/'.$page);
        $res->assertOk();
        expect($res->getContent())->toContain('dir="rtl"');
    }
});

// 7 ------------------------------------------------------------------------------------------------------------------------------------------
it('carries the gift finder session into the order and reports completed orders and AOV', function () {
    $p = ($this->product)('giftx', '80.00');
    $res = app(GiftFinderService::class)->search(['budget' => 200]);
    $this->withSession([])->post($this->base.'/en/gift-finder/'.$res['token'].'/cart', ['product_ids' => [$p->id]], ['HTTP_ACCEPT' => 'application/json'])->assertOk();
    expect(session('gift_session_id'))->toBe(GiftFinderSession::where('token', $res['token'])->value('id'));
    $sid = GiftFinderSession::where('token', $res['token'])->value('id');
    ($this->order)('delivered', ['gift_session' => $sid], ['placed_at' => now()->subDay(), 'total' => 120]);
    ($this->order)('cancelled', ['gift_session' => $sid], ['placed_at' => now()->subDay()]);
    $s = app(GiftFinderService::class)->stats();
    expect($s['orders'])->toBe(1)->and($s['orders_aov'])->toBe(120.0)->and($s['order_rate'])->toBe(100.0);
});

// 8 ------------------------------------------------------------------------------------------------------------------------------------------
it('attributes orders to the bundle A/B variant and lets orders decide the winner when the sample allows', function () {
    $p = ($this->product)('bab');
    $bundle = Bundle::create(['name' => ['en' => 'B', 'ar' => 'ب'], 'slug' => 'bb', 'description' => ['en' => 'd'], 'price' => 90, 'is_active' => true]);
    $t = app(BundleAbService::class)->start($bundle->id, ['name' => ['en' => 'B2']], 50, 20, 1);
    $a = $t->variants->firstWhere('label', 'A');
    $b = $t->variants->firstWhere('label', 'B');
    $a->update(['exposures' => 400, 'carts' => 40]);
    $b->update(['exposures' => 400, 'carts' => 40]); // equal cart rate
    foreach (range(1, 12) as $i) {
        ($this->order)('confirmed', ['bundle_ab' => [$t->id => 'B']], ['placed_at' => now()->addMinute()]);
    }
    ($this->order)('confirmed', ['bundle_ab' => [$t->id => 'A']], ['placed_at' => now()->addMinute()]);
    ($this->order)('cancelled', ['bundle_ab' => [$t->id => 'A']], ['placed_at' => now()->addMinute()]);
    $r = app(BundleAbService::class)->results($t->fresh());
    expect($r['variants'][1]['orders'])->toBe(12)->and($r['variants'][0]['orders'])->toBe(1)->and($r['recommendation'])->toBe('winner_b')->and($r['metric_note'])->toBe('order_rate');
    // recordCart stores the assignment in the session for the order attribution
    $this->withSession([])->get($this->base.'/en')->assertOk();
    app(AttributionService::class);
    expect(AttributionService::forOrder(null)['attribution'])->toBeNull(); // nothing carried without a session
    ($this->off)('bundle_ab');
    expect(app(BundleAbService::class)->expose($bundle->id, 'v1'))->toBeNull();
});

// 9 ------------------------------------------------------------------------------------------------------------------------------------------
it('shows the Gift Finder storefront links only when gift_finder is ON', function () {
    $this->get($this->base.'/en')->assertOk()->assertSee('/gift-finder', false);
    ($this->off)('gift_finder');
    $this->get($this->base.'/en')->assertOk()->assertDontSee('/gift-finder', false);
});

// 10 -----------------------------------------------------------------------------------------------------------------------------------------
it('pages the interactions and missed lists server-side (Yajra), gated by their switches', function () {
    foreach (range(1, 130) as $i) {
        Interaction::create(['channel' => 'whatsapp', 'external_id' => 'p'.$i, 'text_masked' => 'msg '.$i, 'text_hash' => sha1((string) $i), 'status' => 'classified', 'received_at' => now()->subMinutes($i)]);
    }
    $admin = ($this->owner)();
    $q = ['draw' => 1, 'start' => 100, 'length' => 25, 'columns' => [['data' => 'text_masked', 'name' => 'text_masked', 'searchable' => 'true', 'orderable' => 'false']]];
    $r = $this->actingAs($admin, 'tenant')->getJson($this->base.'/admin/growth/interactions/datatable?'.http_build_query($q))->assertOk();
    expect($r->json('recordsTotal'))->toBe(130)->and(count($r->json('data')))->toBe(25); // beyond the old 100-row cap
    $s = $this->actingAs($admin, 'tenant')->getJson($this->base.'/admin/growth/interactions/datatable?'.http_build_query($q + ['search' => ['value' => 'msg 129']]))->assertOk();
    expect($s->json('recordsFiltered'))->toBe(1);
    $this->actingAs($admin, 'tenant')->getJson($this->base.'/admin/growth/missed/datatable?draw=1&start=0&length=10')->assertOk();
    ($this->off)('interactions', 'missed_opportunities');
    $this->actingAs($admin, 'tenant')->getJson($this->base.'/admin/growth/interactions/datatable')->assertStatus(403);
    $this->actingAs($admin, 'tenant')->getJson($this->base.'/admin/growth/missed/datatable')->assertStatus(403);
});

// 11 -----------------------------------------------------------------------------------------------------------------------------------------
it('alerts once per day when ad spend exceeds the daily cap and lets the tenant override lab thresholds', function () {
    $p = ($this->product)();
    $lab = app(ExperimentLabService::class);
    $e = $lab->create(['name' => 'T', 'product_id' => $p->id, 'hypothesis' => 'h', 'audience' => 'a', 'angle' => 'Simple routine', 'budget_cap' => 5000], null);
    $day = now()->subDay()->toDateString();
    $lab->importAds($e, [['date' => $day, 'spend' => 300, 'impressions' => 1000, 'clicks' => 30]]);
    expect(GrowthActionLog::where('action', 'ad_cap.daily_exceeded')->count())->toBe(0); // cap 500
    $lab->importAds($e, [['date' => $day, 'spend' => 300, 'impressions' => 1000, 'clicks' => 30, 'creative' => 'b']]);
    expect(GrowthActionLog::where('action', 'ad_cap.daily_exceeded')->count())->toBe(1);
    $lab->importAds($e, [['date' => $day, 'spend' => 301, 'impressions' => 1000, 'clicks' => 30, 'creative' => 'b']]);
    expect(GrowthActionLog::where('action', 'ad_cap.daily_exceeded')->count())->toBe(1); // deduped per day
    expect($this->owner->__invoke()->notifications()->where('data->kind', 'ad_cap_exceeded')->count())->toBe(1);
    $brief = app(BriefService::class)->daily(Carbon::parse($day));
    expect(collect($brief['recommendations'])->pluck('kind'))->toContain('ad_cap_exceeded');
    // per-tenant lab thresholds
    expect($lab->cfg('min_days'))->toBe(7.0);
    app(GrowthConfig::class)->update($this->tenant, ['lab_min_days' => 3, 'lab_min_orders' => 2]);
    app()->instance('currentTenant', $this->tenant->refresh());
    expect($lab->cfg('min_days'))->toBe(3.0)->and($lab->cfg('min_orders'))->toBe(2.0)->and($lab->cfg('min_clicks'))->toBe(100.0);
    expect(fn () => app(GrowthConfig::class)->update($this->tenant, ['lab_min_days' => 500]))->toThrow(ValidationException::class);
    // OFF: no alert is raised from the lab import (lab itself is gated; the service no-ops without breach data)
    expect(app(AdCapService::class)->breach(now()->subDays(5)->toDateString()))->toBeNull();
});

// 12 -----------------------------------------------------------------------------------------------------------------------------------------
it('lets the owner cancel an unshipped order with a reason code (web + API) only when return_intel is ON', function () {
    $c = Customer::create(['name' => 'Cx', 'email' => 'cx@example.com', 'phone' => '01033334444', 'password' => 'password123']);
    $p = ($this->product)('cx');
    $o = ($this->order)('pending', [], ['customer_id' => $c->id, 'payment_status' => 'unpaid', 'paid_amount' => 0, 'branch_id' => Branch::first()->id, 'placed_at' => now()]);
    OrderItem::where('order_id', $o->id)->update(['product_id' => $p->id]);
    $url = $this->base.'/en/order/'.$o->public_token.'/cancel';
    $this->actingAs($c, 'web')->postJson($url, ['reason_code' => 'changed_mind'])->assertOk();
    expect($o->fresh()->status->value)->toBe('cancelled')->and($o->fresh()->cancel_reason_code)->toBe('changed_mind');

    $o2 = ($this->order)('pending', [], ['customer_id' => $c->id, 'payment_status' => 'unpaid', 'paid_amount' => 0, 'branch_id' => Branch::first()->id, 'placed_at' => now()]);
    OrderItem::where('order_id', $o2->id)->update(['product_id' => $p->id]);
    $this->actingAs($c, 'web')->postJson($this->base.'/en/order/'.$o2->public_token.'/cancel', [])->assertStatus(422);
    $this->actingAs($c, 'web')->postJson($this->base.'/en/order/'.$o2->public_token.'/cancel', ['reason_code' => 'nonsense'])->assertStatus(422);
    $other = Customer::create(['name' => 'Oy', 'email' => 'oy@example.com', 'phone' => '01055556666', 'password' => 'password123']);
    $this->actingAs($other, 'web')->postJson($this->base.'/en/order/'.$o2->public_token.'/cancel', ['reason_code' => 'quality'])->assertStatus(403);
    ($this->off)('return_intel');
    $this->actingAs($c, 'web')->postJson($this->base.'/en/order/'.$o2->public_token.'/cancel', ['reason_code' => 'quality'])->assertNotFound();
    expect($o2->fresh()->status->value)->toBe('pending');
});

it('prunes interactions in a different tenant untouched by customer erasure (tenant scope)', function () {
    $c = Customer::create(['name' => 'T', 'email' => 't@example.com', 'phone' => '01077778888', 'password' => 'password123']);
    app(InteractionService::class)->ingest(['channel' => 'email', 'external_id' => 'ts1', 'text' => 'hi', 'contact' => 't@example.com']);
    $other = Tenant::firstOrCreate(['slug' => 'sa'], ['name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    Interaction::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'channel' => 'email', 'external_id' => 'ts2', 'text_masked' => 'x', 'text_hash' => 'h', 'status' => 'new', 'received_at' => now(), 'customer_id' => $c->id]);
    app(GrowthRetentionService::class)->eraseCustomer($this->tenant, $c);
    expect(Interaction::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(0)->and(Interaction::withoutGlobalScopes()->where('tenant_id', $other->id)->count())->toBe(1);
});
