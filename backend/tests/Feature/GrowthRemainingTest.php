<?php

// Growth N10: remaining items (docs/plans/n8n-growth-remaining.md): 1 missed edit, 2 API attribution, 5 classifier eval + AI fallback, 6 FIFO stock age, 8 Hijri confirm, 9 n8n selftest.

use App\Enums\StockMovementType as T;
use App\Models\AiModel;
use App\Models\Branch;
use App\Models\Cart;
use App\Models\ImprovementTask;
use App\Models\Interaction;
use App\Models\MissedOpportunity;
use App\Models\Product;
use App\Models\SeasonalEvent;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\Ai\AiAccess;
use App\Services\Ai\AiGateway;
use App\Services\Ai\StudioService;
use App\Services\Ai\Support\AiResponse;
use App\Services\BarcodeService;
use App\Services\Growth\AttributionService;
use App\Services\Growth\GrowthAccess;
use App\Services\Growth\GrowthFeatures;
use App\Services\Growth\InteractionService;
use App\Services\Growth\InventoryDecisionService;
use App\Services\Growth\SeasonalCalendarService;
use App\Services\Growth\StockAgeService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config(['growth.classifier.require_real_eval' => false]); // the real-data gate has its own tests (GrowthOpenItemsTest)
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
        foreach ($keys as $k) {
            $s['growth']['features'][$k] = false;
        }
        $t->update(['settings' => $s]);
        app()->instance('currentTenant', $t->refresh());
    };
});

afterEach(fn () => Carbon::setTestNow());

// 1 ------------------------------------------------------------------------------------------------------------------------------------------
it('edits owner, due date, note and status of a missed opportunity from the modal endpoint (ON) and is refused when OFF', function () {
    $m = MissedOpportunity::create(['rule' => 'abandoned_carts', 'dedupe_key' => 'k1', 'title' => 'T', 'evidence' => [], 'probable_cause' => 'c', 'confidence' => 'low', 'suggestion' => 's', 'status' => 'open']);
    $page = $this->actingAs(($this->owner)(), 'tenant')->get("{$this->base}/admin/growth/missed")->assertOk();
    $page->assertSee('missed-edit-form', false);
    $this->actingAs(($this->owner)(), 'tenant')->putJson("{$this->base}/admin/growth/missed/{$m->id}", ['owner' => 'Mona', 'due_on' => '2026-12-01', 'result_note' => 'called supplier', 'status' => 'accepted'])->assertOk();
    $m->refresh();
    expect($m->owner)->toBe('Mona')->and($m->due_on->toDateString())->toBe('2026-12-01')->and($m->result_note)->toBe('called supplier')->and($m->status)->toBe('accepted');
    $this->assertDatabaseHas('growth_action_log', ['action' => 'missed.updated']);
    ($this->off)('missed_opportunities');
    $this->actingAs(($this->owner)(), 'tenant')->putJson("{$this->base}/admin/growth/missed/{$m->id}", ['owner' => 'X'])->assertForbidden();
    expect($m->fresh()->owner)->toBe('Mona');
});

// 2 ------------------------------------------------------------------------------------------------------------------------------------------
it('parses X-Attribution safely (whitelist, sanitising, size cap, garbage ignored)', function () {
    $enc = fn (array $a) => rtrim(strtr(base64_encode(json_encode($a)), '+/', '-_'), '=');
    $r = AttributionService::fromHeader($enc(['utm_source' => 'facebook', 'utm_medium' => 'cpc', 'utm_content' => 'a<b>', 'evil' => 'x', 'gift_session' => 7, 'ab' => 'B']));
    expect($r['attr']['utm_source'])->toBe('facebook')->and($r['attr']['channel'])->toBe('paid')->and($r['attr'])->not->toHaveKey('evil')
        ->and($r['attr']['utm_content'])->toBe('ab')->and($r['gift_session'])->toBe(7)->and($r['bundle_ab'])->toBe('B');
    expect(AttributionService::fromHeader('%%%not-base64'))->toBeNull()->and(AttributionService::fromHeader(str_repeat('a', 1025)))->toBeNull()->and(AttributionService::fromHeader($enc(['x' => 1])))->toBeNull();
});

it('stores API first-touch attribution on the cart and a later header does not overwrite it', function () {
    $pr = Product::create(['type' => 'simple', 'name' => ['en' => 'Item', 'ar' => 'صنف'], 'description' => ['en' => 'x'], 'slug' => 'ap', 'sku' => 'AP', 'barcode' => app(BarcodeService::class)->generateUnique()]);
    TenantProduct::create(['tenant_id' => $this->tenant->id, 'product_id' => $pr->id, 'price' => '100.00', 'cost' => 50, 'is_active' => true]);
    app(StockLedger::class)->record(Branch::first()->id, $pr->id, 0, T::PURCHASE_IN, 20, 50);
    $enc = fn (array $a) => rtrim(strtr(base64_encode(json_encode($a)), '+/', '-_'), '=');
    $r = $this->postJson("{$this->base}/api/v1/cart/items", ['product_id' => $pr->id, 'variant_id' => 0, 'quantity' => 1], ['X-Attribution' => $enc(['utm_source' => 'instagram', 'utm_campaign' => 'eid'])])->assertOk();
    $token = $r->headers->get('X-Cart-Token');
    $cart = Cart::where('token', $token)->first();
    expect($cart->attribution['utm_source'])->toBe('instagram')->and($cart->attribution['channel'])->toBe('social');
    $this->postJson("{$this->base}/api/v1/cart/items", ['product_id' => $pr->id, 'variant_id' => 0, 'quantity' => 1], ['X-Cart-Token' => $token, 'X-Attribution' => $enc(['utm_source' => 'google', 'utm_medium' => 'cpc'])])->assertOk();
    expect($cart->fresh()->attribution['utm_source'])->toBe('instagram');
    // a request with the cart but no header restores the stored first touch for checkout
    $this->getJson("{$this->base}/api/v1/cart", ['X-Cart-Token' => $token])->assertOk();
    expect(session('growth_attr')['utm_source'] ?? null)->toBe('instagram');
});

// 5 ------------------------------------------------------------------------------------------------------------------------------------------
it('measures the keyword classifier on the labelled set and meets the configured targets', function () {
    $set = json_decode(file_get_contents(base_path('tests/Fixtures/growth/interactions_labelled.json')), true);
    expect(count($set))->toBeGreaterThanOrEqual(150);
    $res = app(InteractionService::class)->evaluate($set);
    foreach (config('growth.classifier.target_accuracy') as $f => $t) {
        expect($res['accuracy'][$f])->toBeGreaterThanOrEqual($t);
    }
    $this->artisan('growth:eval-classifier')->assertSuccessful();
});

function n10Ai($test, ?string $json, bool $throws = false, bool $expectCall = true): void
{
    $bind = function (string $cls, callable $f) {
        $m = Mockery::mock($cls);
        $f($m);
        app()->instance($cls, $m);
    };
    $bind(AiAccess::class, fn ($m) => $m->shouldReceive('enabled')->andReturn(true));
    $bind(StudioService::class, fn ($m) => $m->shouldReceive('model')->andReturn(new AiModel(['model_id' => 'fake'])));
    $bind(AiGateway::class, function ($m) use ($json, $throws, $expectCall) {
        if (! $expectCall) {
            $m->shouldNotReceive('chat');

            return;
        }
        $e = $m->shouldReceive('chat')->once();
        $throws ? $e->andThrow(new RuntimeException('quota')) : $e->andReturn(new AiResponse(text: (string) $json));
    });
}

it('uses the AI classifier only for low-confidence messages, validates its output and never blocks ingest', function () {
    $svc = fn () => app(InteractionService::class);
    n10Ai($this, '{"category":"price","intent":"bogus","urgency":"high","sentiment":"positive"}');
    [$i] = $svc()->ingest(['channel' => 'whatsapp', 'external_id' => 'a1', 'text' => 'zzz qqq www']);
    expect($i->category)->toBe('price')->and($i->urgency)->toBe('high')->and($i->sentiment)->toBe('positive')->and($i->intent)->toBe('other'); // invalid intent dropped -> keyword fallback
});

it('does not call the AI for a confident keyword match, when the switch is OFF, or let a gateway failure block ingest', function () {
    n10Ai($this, null, expectCall: false);
    [$i] = app(InteractionService::class)->ingest(['channel' => 'whatsapp', 'external_id' => 'b1', 'text' => 'How much is the price?']);
    expect($i->category)->toBe('price');
    ($this->off)('ai_classify');
    [$j] = app(InteractionService::class)->ingest(['channel' => 'whatsapp', 'external_id' => 'b2', 'text' => 'zzz qqq www']);
    expect($j->category)->toBeNull();
});

it('keeps ingesting when the AI gateway throws', function () {
    n10Ai($this, null, throws: true);
    [$i, $created] = app(InteractionService::class)->ingest(['channel' => 'whatsapp', 'external_id' => 'c1', 'text' => 'zzz qqq www']);
    expect($created)->toBeTrue()->and(Interaction::count())->toBe(1);
});

// 6 ------------------------------------------------------------------------------------------------------------------------------------------
it('computes FIFO stock age layers (golden values)', function () {
    $now = Carbon::parse('2026-10-27');
    $mv = fn (T $t, int $q, int $daysAgo, ?float $c = null) => ['type' => $t, 'quantity' => $q, 'unit_cost' => $c, 'at' => $now->copy()->subDays($daysAgo)];
    // in 10 @100d, in 10 @10d, out 4: oldest layer keeps 6, newest 10
    $r = StockAgeService::layers(collect([$mv(T::SALE_OUT, 4, 5), $mv(T::PURCHASE_IN, 10, 100, 5.0), $mv(T::PURCHASE_IN, 10, 10, 8.0)]), 16, $now);
    expect($r['oldest_age_days'])->toBe(100)->and($r['weighted_age_days'])->toBe(43.8)->and($r['unknown_qty'])->toBe(0)->and(array_column($r['layers'], 'qty'))->toBe([6, 10]);
    expect(StockAgeService::valueOlderThan($r['layers'], 90))->toBe(30.0)->and(StockAgeService::valueOlderThan($r['layers'], 5))->toBe(110.0);
    // all of layer 1 and part of layer 2 sold: the old stock is gone, age drops to the newer layer
    $r = StockAgeService::layers(collect([$mv(T::PURCHASE_IN, 10, 100), $mv(T::PURCHASE_IN, 5, 50), $mv(T::SALE_OUT, 12, 40)]), 3, $now);
    expect($r['oldest_age_days'])->toBe(50)->and($r['layers'])->toHaveCount(1)->and($r['layers'][0]['qty'])->toBe(3);
    // on-hand lower than the ledger says (shrinkage not recorded): keep the NEWEST units
    $r = StockAgeService::layers(collect([$mv(T::PURCHASE_IN, 10, 100), $mv(T::PURCHASE_IN, 10, 10)]), 4, $now);
    expect($r['oldest_age_days'])->toBe(10)->and($r['layers'][0]['qty'])->toBe(4);
    // on-hand not explained by receipts = unknown, never an invented age; transfers ignored
    $r = StockAgeService::layers(collect([$mv(T::PURCHASE_IN, 6, 20), $mv(T::TRANSFER_OUT, 6, 3), $mv(T::TRANSFER_IN, 6, 3)]), 10, $now);
    expect($r['unknown_qty'])->toBe(4)->and($r['oldest_age_days'])->toBe(20);
    expect(StockAgeService::layers(collect(), 0, $now)['oldest_age_days'])->toBeNull();
});

it('feeds FIFO age and aged value into the inventory engine', function () {
    $pr = Product::create(['type' => 'simple', 'name' => ['en' => 'Old', 'ar' => 'قديم'], 'description' => ['en' => 'x'], 'slug' => 'old', 'sku' => 'OLD', 'barcode' => app(BarcodeService::class)->generateUnique()]);
    TenantProduct::create(['tenant_id' => $this->tenant->id, 'product_id' => $pr->id, 'price' => '100.00', 'cost' => 50, 'is_active' => true]);
    $b = Branch::first()->id;
    app(StockLedger::class)->record($b, $pr->id, 0, T::PURCHASE_IN, 10, 50);
    StockMovement::query()->update(['created_at' => now()->subDays(120)]);
    app(StockLedger::class)->record($b, $pr->id, 0, T::PURCHASE_IN, 5, 50);
    $res = app(InventoryDecisionService::class)->analyze();
    $row = collect($res['rows'])->firstWhere('product_id', $pr->id);
    expect($row['age_days'])->toBe(120)->and($row['aged_value'])->toBe(500.0)->and($row['weighted_age_days'])->toBeGreaterThan(70.0)->and($res['summary']['aged_value'])->toBe(500.0);
});

// 8 ------------------------------------------------------------------------------------------------------------------------------------------
it('flags unconfirmed lunar dates, creates 7-day and 2-day reminders once, and stops after confirmation', function () {
    $svc = app(SeasonalCalendarService::class);
    Carbon::setTestNow('2026-01-01');
    $svc->ensureYear(2026);
    $e = SeasonalEvent::where('key', 'ramadan')->where('year', 2026)->first();
    expect($e->needsConfirmation())->toBeTrue()->and(SeasonalEvent::where('key', 'valentine')->first()->needsConfirmation())->toBeFalse();
    $page = $this->actingAs(($this->owner)(), 'tenant')->get("{$this->base}/admin/growth/seasonal?year=2026")->assertOk();
    $page->assertSee(__('growth_hub.seasonal.confirm_date'));
    Carbon::setTestNow($e->starts_on->copy()->subDays(8));
    expect($svc->remindUnconfirmed())->toBe(0);
    Carbon::setTestNow($e->starts_on->copy()->subDays(6));
    expect($svc->remindUnconfirmed())->toBe(1)->and($svc->remindUnconfirmed())->toBe(0);
    Carbon::setTestNow($e->starts_on->copy()->subDays(2));
    expect($svc->remindUnconfirmed())->toBe(1)->and(ImprovementTask::where('rule', 'hijri_confirm')->count())->toBe(2);
    $this->actingAs(($this->owner)(), 'tenant')->postJson("{$this->base}/admin/growth/seasonal/{$e->id}/confirm")->assertOk();
    $e->refresh();
    expect($e->date_confirmed)->toBeTrue()->and($e->confirmed_at)->not->toBeNull();
    Carbon::setTestNow($e->starts_on->copy()->subDay());
    expect($svc->remindUnconfirmed())->toBe(0);
});

it('treats an admin edit of the lunar dates as confirmation, and gates confirm/jobs by the seasonal switch', function () {
    Carbon::setTestNow('2026-01-01');
    $svc = app(SeasonalCalendarService::class);
    $svc->ensureYear(2026);
    $e = SeasonalEvent::where('key', 'eid_adha')->where('year', 2026)->first();
    $svc->update($e->id, ['starts_on' => $e->starts_on->copy()->addDay()->toDateString(), 'ends_on' => $e->ends_on->copy()->addDay()->toDateString(), 'lead_time_days' => 21], 1);
    expect($e->fresh()->date_confirmed)->toBeTrue();
    $r = SeasonalEvent::where('key', 'ramadan')->where('year', 2026)->first();
    Carbon::setTestNow($r->starts_on->copy()->subDays(3));
    ($this->off)('seasonal_calendar');
    $this->artisan('growth:operate')->assertSuccessful();
    expect(ImprovementTask::where('rule', 'hijri_confirm')->count())->toBe(0);
    $this->actingAs(($this->owner)(), 'tenant')->postJson("{$this->base}/admin/growth/seasonal/{$r->id}/confirm")->assertForbidden();
});

// 9 ------------------------------------------------------------------------------------------------------------------------------------------
it('replays every n8n workflow HTTP node against the real routes (ON and OFF)', function () {
    if (! trim((string) shell_exec('command -v node'))) {
        $this->markTestSkipped('node not installed');
    }
    $this->artisan('growth:n8n-selftest', ['--tenant' => 'egy'])->expectsOutputToContain('0 problem(s)')->assertSuccessful();
});
