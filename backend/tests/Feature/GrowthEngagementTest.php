<?php

use App\Enums\StockMovementType as T;
use App\Models\Branch;
use App\Models\ImprovementTask;
use App\Models\Interaction;
use App\Models\MissedOpportunity;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReputationAlert;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Models\TrackedLink;
use App\Services\Growth\BriefService;
use App\Services\Growth\GrowthAccess;
use App\Services\Growth\GrowthFeatures;
use App\Services\Growth\IntegrationClientService;
use App\Services\Growth\InteractionService;
use App\Services\Growth\MissedOpportunityService;
use App\Services\Growth\ReputationService;
use App\Services\Growth\SignatureService;
use App\Services\Growth\SuggestedReplyService;
use App\Services\Growth\TrackedLinkService;
use App\Services\Growth\VocService;
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
    $this->level = fn (int $l) => app(InteractionService::class)->setReplyLevel($l, null);
    $this->product = function (string $slug = 'serum', string $price = '100.00') {
        $pr = Product::create(['type' => 'simple', 'name' => ['en' => 'Item '.$slug, 'ar' => 'صنف '.$slug], 'description' => ['en' => 'x'], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
        TenantProduct::create(['tenant_id' => $this->tenant->id, 'product_id' => $pr->id, 'price' => $price, 'cost' => 50, 'is_active' => true]);
        app(StockLedger::class)->record(Branch::first()->id, $pr->id, 0, T::PURCHASE_IN, 10, 50);

        return $pr;
    };
    $this->ix = fn (array $o = []) => app(InteractionService::class)->ingest($o + ['channel' => 'whatsapp', 'external_id' => uniqid('e'), 'text' => 'hello', 'received_at' => now()->toDateTimeString()])[0];
});

function engClient(array $scopes, ?Tenant $tenant = null): array
{
    $prev = current_tenant();
    app()->instance('currentTenant', $tenant ?? $prev);
    $r = app(IntegrationClientService::class)->create('n8n', $scopes, null, null);
    app()->instance('currentTenant', $prev);

    return $r;
}

function engCall($test, string $method, string $path, ?array $body, array $c, array $headers = [])
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

it('ingests idempotently, masks PII before storage and validates classification', function () {
    $c = engClient(['ingest:write', 'read']);
    $p = ($this->product)();
    $body = ['channel' => 'whatsapp', 'external_id' => 'm1', 'text' => 'call me 01012345678 or a@b.com about price', 'product_id' => $p->id, 'category' => 'price'];
    $r = engCall($this, 'POST', 'interactions', $body, $c, ['Idempotency-Key' => 'k1'])->assertStatus(201);
    engCall($this, 'POST', 'interactions', $body, $c, ['Idempotency-Key' => 'k1'])->assertStatus(201); // replay
    engCall($this, 'POST', 'interactions', $body, $c, ['Idempotency-Key' => 'k2'])->assertStatus(200)->assertJsonPath('data.created', false); // same external id
    expect(Interaction::count())->toBe(1);
    $i = Interaction::first();
    expect($i->text_masked)->not->toContain('01012345678')->not->toContain('a@b.com')->and($i->text_masked)->toContain('[phone]')->and($i->category)->toBe('price');
    engCall($this, 'POST', 'interactions', ['category' => 'bogus'] + $body, $c, ['Idempotency-Key' => 'k3'])->assertStatus(422);
    $ro = engClient(['read']);
    engCall($this, 'POST', 'interactions', $body, $ro, ['Idempotency-Key' => 'k4'])->assertStatus(403); // scope
});

it('isolates tenants: other tenant cannot see or reply to my interactions', function () {
    $i = ($this->ix)(['text' => 'price?']);
    $c2 = engClient(['read', 'ingest:write'], $this->other);
    engCall($this, 'GET', "interactions/{$i->id}/suggested-reply", null, $c2)->assertStatus(404);
    engCall($this, 'POST', "interactions/{$i->id}/responded", null, $c2)->assertStatus(404);
    app()->instance('currentTenant', $this->other);
    expect(Interaction::count())->toBe(0)->and(app(InteractionService::class)->unanswered()->count())->toBe(0);
});

it('every N4 surface is gated by its switch (API 403, admin 404, short link 404) and dependencies cascade', function () {
    $c = engClient(['read', 'ingest:write']);
    $p = ($this->product)();
    $l = app(TrackedLinkService::class)->create($p->id, 'facebook', null, null);
    $owner = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
    $this->actingAs($owner, 'tenant');
    foreach (['interactions', 'voc', 'reputation', 'missed'] as $pg) {
        $this->get("{$this->base}/admin/growth/$pg")->assertOk();
    }
    $this->get("{$this->base}/l/{$l->code}")->assertRedirect();
    ($this->off)('comment_to_cart');
    $this->get("{$this->base}/l/{$l->code}")->assertNotFound();
    engCall($this, 'POST', 'tracked-links', ['product_id' => $p->id], $c, ['Idempotency-Key' => 'x'])->assertStatus(403)->assertJsonPath('error', 'feature_disabled');
    ($this->off)('reputation_radar');
    $this->actingAs($owner, 'tenant');
    $this->get("{$this->base}/admin/growth/reputation")->assertNotFound();
    engCall($this, 'GET', 'reputation/alerts', null, $c)->assertStatus(403);
    ($this->off)('missed_opportunities');
    $this->actingAs($owner, 'tenant');
    $this->get("{$this->base}/admin/growth/missed")->assertNotFound();
    expect(MissedOpportunity::count())->toBe(0);
    ($this->off)('interactions'); // voc depends on interactions
    $this->actingAs($owner, 'tenant');
    $this->get("{$this->base}/admin/growth/voc")->assertNotFound();
    $this->actingAs($owner, 'tenant');
    $this->get("{$this->base}/admin/growth/interactions")->assertNotFound();
    engCall($this, 'POST', 'interactions', ['channel' => 'whatsapp', 'external_id' => 'z', 'text' => 'x'], $c, ['Idempotency-Key' => 'y'])->assertStatus(403);
    expect(app(GrowthFeatures::class)->active('voc'))->toBeFalse();
    $this->artisan('growth:engage')->assertSuccessful(); // nothing to do, no error
});

it('classifies with the fallback, escalates complaints / safety / refunds and honours opt-out and the 24h window', function () {
    $a = ($this->ix)(['text' => 'كام سعر المنتج؟']);
    expect($a->category)->toBe('price')->and($a->status)->toBe('classified')->and($a->language)->toBe('ar')->and($a->window_expires_at)->not->toBeNull();
    $b = ($this->ix)(['text' => 'I got an allergy rash from this']);
    expect($b->escalation_reason)->toBe('safety')->and($b->status)->toBe('escalated');
    $c = ($this->ix)(['text' => 'I want a refund now']);
    expect($c->escalation_reason)->toBe('refund');
    $d = ($this->ix)(['text' => 'please unsubscribe me']);
    expect($d->opted_out)->toBeTrue();
    $e = ($this->ix)(['channel' => 'review', 'text' => 'great']);
    expect($e->window_expires_at)->toBeNull();
    $old = ($this->ix)(['received_at' => now()->subDays(2)->toDateTimeString()]);
    expect(app(InteractionService::class)->windowOpen($old))->toBeFalse();
});

it('reply maturity: L1 classifies only, L2 suggests from live facts, escalations stay human, opt-out gets nothing', function () {
    $p = ($this->product)('glow', '149.50');
    $i = ($this->ix)(['text' => 'how much is it', 'product_id' => $p->id, 'category' => 'price']);
    $svc = app(SuggestedReplyService::class);
    expect($svc->suggest($i)['mode'])->toBe('classify_only');
    ($this->level)(2);
    $r = $svc->suggest($i);
    expect($r['mode'])->toBe('suggest')->and($r['reply'])->toContain('149.50')->and($r['auto_allowed'])->toBeFalse();
    $stock = ($this->ix)(['text' => 'is it available', 'product_id' => $p->id, 'category' => 'availability']);
    expect($svc->suggest($stock)['reply'])->toContain('in stock');
    $esc = ($this->ix)(['text' => 'this is terrible, I want my money back refund', 'product_id' => $p->id]);
    expect($svc->suggest($esc))->toMatchArray(['mode' => 'human', 'reply' => null]);
    $nop = ($this->ix)(['text' => 'tell me something', 'category' => 'usage']);
    expect($svc->suggest($nop)['reason'])->toBe('missing_data');
    $opt = ($this->ix)(['text' => 'stop', 'product_id' => $p->id]);
    expect($svc->suggest($opt)['mode'])->toBe('none');
});

it('auto-reply only for allowlisted FAQs at L3 with the feature on and no reputation pause', function () {
    $p = ($this->product)();
    $faq = app(\App\Services\Growth\BrandMemoryService::class)->save(['kind' => 'faq', 'title' => 'Do you deliver to Cairo', 'body' => 'Yes, we deliver to Cairo in 1-2 days.'], null, null);
    $i = ($this->ix)(['text' => 'Hi, do you deliver to Cairo?', 'product_id' => $p->id]);
    $svc = app(SuggestedReplyService::class);
    ($this->level)(3);
    expect($svc->suggest($i)['mode'])->toBe('suggest')->and($svc->suggest($i)['faq_key'])->toBe('faq'.$faq->id); // not allowlisted
    $t = current_tenant();
    app(\App\Services\Growth\GrowthConfig::class)->update($t, ['auto_reply_allowlist' => 'faq'.$faq->id]);
    app()->instance('currentTenant', $t->refresh());
    expect($svc->suggest($i)['mode'])->toBe('auto');
    ReputationAlert::create(['product_id' => $p->id, 'topic' => 'shipping', 'rate' => 50, 'baseline_rate' => 5, 'z_score' => 5, 'sample' => 10, 'status' => 'open', 'detected_on' => now()]);
    expect($svc->suggest($i)['mode'])->toBe('suggest'); // paused
    ReputationAlert::query()->delete();
    ($this->off)('auto_reply');
    expect($svc->suggest($i)['mode'])->toBe('suggest');
    ($this->level)(2);
    app(\App\Services\Growth\GrowthFeatures::class)->update(current_tenant(), ['auto_reply' => true]);
    expect($svc->suggest($i)['mode'])->toBe('suggest'); // level too low
});

it('reputation radar needs a min sample and a real spike, then pauses auto-reply; it never deletes interactions', function () {
    $p = ($this->product)();
    for ($k = 0; $k < 12; $k++) { // baseline: 1 complaint in 12
        ($this->ix)(['product_id' => $p->id, 'text' => 'ok '.$k, 'sentiment' => $k === 0 ? 'negative' : 'neutral', 'received_at' => now()->subDays(20)->toDateTimeString()]);
    }
    for ($k = 0; $k < 2; $k++) {
        ($this->ix)(['product_id' => $p->id, 'text' => 'bad late', 'sentiment' => 'negative', 'category' => 'shipping']);
    }
    expect(app(ReputationService::class)->detect())->toBe(0); // below min sample
    for ($k = 0; $k < 4; $k++) {
        ($this->ix)(['product_id' => $p->id, 'text' => 'bad late delivery', 'sentiment' => 'negative', 'category' => 'shipping']);
    }
    ($this->ix)(['product_id' => $p->id, 'text' => 'fine', 'sentiment' => 'neutral']);
    expect(app(ReputationService::class)->detect())->toBe(1);
    $a = ReputationAlert::first();
    expect($a->pause_auto_reply)->toBeTrue()->and($a->topic)->toBe('shipping')->and($a->z_score)->toBeGreaterThan(2.0)->and($a->examples[0]['count'])->toBeGreaterThanOrEqual(4);
    app(ReputationService::class)->detect();
    expect(ReputationAlert::count())->toBe(1)->and(Interaction::count())->toBe(19);
    app(ReputationService::class)->resolve($a->id, null);
    expect($a->fresh()->pause_auto_reply)->toBeFalse();
});

it('voc aggregates, detects recurring objections and creates one task per product+topic with before/after', function () {
    $p = ($this->product)();
    for ($k = 0; $k < 3; $k++) {
        ($this->ix)(['product_id' => $p->id, 'text' => 'how much '.$k, 'category' => 'price', 'channel' => $k ? 'facebook' : 'whatsapp']);
    }
    ($this->ix)(['product_id' => $p->id, 'text' => 'photos?', 'category' => 'photos']);
    $v = app(VocService::class);
    $agg = $v->aggregate();
    expect($agg['total'])->toBe(4)->and($agg['by_category']['price'])->toBe(3)->and($agg['recurring_objections'])->toHaveCount(1);
    expect($v->generateTasks())->toBe(1)->and($v->generateTasks())->toBe(0);
    $t = ImprovementTask::first();
    expect($t->before_value)->toEqual(3)->and($t->topic)->toBe('price');
    $v->updateTask($t->id, ['status' => 'done'], null);
    expect($t->fresh()->after_value)->toEqual(3)->and($t->fresh()->status)->toBe('done');
    app()->instance('currentTenant', $this->other);
    expect(ImprovementTask::count())->toBe(0)->and($v->aggregate()['total'])->toBe(0);
});

it('missed opportunities: rule with evidence + one experiment, idempotent per week, tenant isolated, weekly endpoint', function () {
    $p = ($this->product)();
    for ($k = 0; $k < 6; $k++) {
        ($this->ix)(['product_id' => $p->id, 'text' => 'price? '.$k, 'category' => 'price', 'intent' => 'purchase_intent']);
    }
    ($this->ix)(['text' => 'old', 'received_at' => now()->subDays(3)->toDateTimeString()]);
    $svc = app(MissedOpportunityService::class);
    $n = $svc->scan();
    expect($n)->toBeGreaterThanOrEqual(3);
    $rules = MissedOpportunity::pluck('rule')->all();
    expect($rules)->toContain('inquiries_gt_orders', 'demand_no_content', 'recurring_objection', 'unanswered_sla');
    $m = MissedOpportunity::where('rule', 'inquiries_gt_orders')->first();
    expect($m->evidence[0]['metric'])->toBe('inquiries_30d')->and($m->evidence[0]['value'])->toBe(6)->and($m->suggestion)->not->toBe('')->and($m->due_on)->not->toBeNull()->and($m->confidence)->toBe('medium');
    expect($svc->scan())->toBe(0);
    $svc->update($m->id, ['status' => 'dismissed', 'owner' => 'Mona', 'result_note' => 'n/a'], null);
    expect($svc->scan())->toBe(0)->and(MissedOpportunity::find($m->id)->owner)->toBe('Mona');
    $c = engClient(['read']);
    engCall($this, 'GET', 'missed-opportunities/weekly', null, $c)->assertOk()->assertJsonPath('data.items.0.confidence', 'high');
    app()->instance('currentTenant', $this->other);
    expect($svc->weekly()['items'])->toBe([]);
});

it('missed opportunities: orders satisfy the inquiry rule so it is not raised', function () {
    $p = ($this->product)();
    for ($k = 0; $k < 5; $k++) {
        ($this->ix)(['product_id' => $p->id, 'text' => 'buy '.$k, 'intent' => 'purchase_intent']);
    }
    for ($k = 0; $k < 2; $k++) {
        $o = new Order;
        $o->forceFill(['number' => 'N4'.$k, 'public_token' => uniqid('t', true), 'customer_name' => 'X', 'customer_phone' => '0100', 'address' => 'a', 'status' => 'delivered', 'payment_status' => 'paid', 'subtotal' => 100, 'discount' => 0, 'shipping' => 0, 'shipping_net' => 0, 'shipping_cost' => 0, 'tax' => 0, 'payment_fee' => 0, 'payment_fee_charged' => 0, 'cod_fee' => 0, 'total' => 100, 'cogs' => 50, 'affiliate_commission' => 0, 'packaging_cost' => 0, 'currency' => 'EGP', 'locale' => 'en', 'placed_at' => now()])->save();
        (new \App\Models\OrderItem)->forceFill(['order_id' => $o->id, 'product_id' => $p->id, 'name' => 'P', 'unit_price' => 100, 'unit_cost' => 50, 'quantity' => 1, 'line_subtotal' => 100, 'line_discount' => 0])->save();
    }
    app(MissedOpportunityService::class)->scan();
    expect(MissedOpportunity::where('rule', 'inquiries_gt_orders')->count())->toBe(0);
});

it('tracked link redirects with UTM, counts human clicks only, attributes orders and is tenant scoped', function () {
    $p = ($this->product)('lip');
    $svc = app(TrackedLinkService::class);
    $l = $svc->create($p->id, 'instagram', null, 'eid post');
    $res = $this->call('GET', "{$this->base}/l/{$l->code}", [], [], [], ['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120']);
    $res->assertRedirect();
    $loc = $res->headers->get('Location');
    expect($loc)->toContain('/product/lip')->toContain('utm_source=instagram')->toContain('utm_content=tl'.$l->code);
    $this->call('GET', "{$this->base}/l/{$l->code}", [], [], [], ['HTTP_USER_AGENT' => 'Googlebot/2.1'])->assertRedirect();
    expect($l->fresh()->clicks)->toBe(1);
    $o = new Order;
    $o->forceFill(['number' => 'TL1', 'public_token' => uniqid('t', true), 'customer_name' => 'X', 'customer_phone' => '0100', 'address' => 'a', 'status' => 'delivered', 'payment_status' => 'paid', 'subtotal' => 80, 'discount' => 0, 'shipping' => 0, 'shipping_net' => 0, 'shipping_cost' => 0, 'tax' => 0, 'payment_fee' => 0, 'payment_fee_charged' => 0, 'cod_fee' => 0, 'total' => 80, 'cogs' => 40, 'affiliate_commission' => 0, 'packaging_cost' => 0, 'currency' => 'EGP', 'locale' => 'en', 'placed_at' => now(), 'channel' => 'social', 'attribution' => ['utm_content' => 'tl'.$l->code]])->save();
    expect($svc->stats($l->fresh()))->toBe(['clicks' => 1, 'orders' => 1, 'revenue' => 80.0]);
    app()->instance('currentTenant', $this->other);
    expect(TrackedLink::count())->toBe(0);
    $this->call('GET', 'http://sa.3alraf.test/l/'.$l->code)->assertNotFound();
    expect(fn () => $svc->create($p->id, null, null, null))->toThrow(\Illuminate\Validation\ValidationException::class); // not this tenant's product
});

it('owner brief conversations section: not available when off, real numbers when on', function () {
    $p = ($this->product)();
    ($this->ix)(['product_id' => $p->id, 'text' => 'terrible service', 'category' => 'shipping', 'sentiment' => 'negative', 'received_at' => now()->subDays(3)->toDateTimeString()]);
    $brief = app(BriefService::class)->daily();
    expect($brief['conversations_and_complaints'])->toMatchArray(['available' => true]);
    $w = app(BriefService::class)->weekly();
    expect($w['conversations_and_complaints']['available'])->toBeTrue();
    ($this->off)('interactions');
    expect(app(BriefService::class)->daily()['conversations_and_complaints'])->toBe(['available' => false]);
});
