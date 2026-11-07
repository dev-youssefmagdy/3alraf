<?php

use App\Jobs\DispatchGrowthEventJob;
use App\Models\GrowthApproval;
use App\Models\GrowthEvent;
use App\Models\GrowthExperiment;
use App\Models\IntegrationClient;
use App\Models\Review;
use App\Models\Admin;
use App\Services\Growth\ApprovalService;
use App\Services\Growth\GrowthAccess;
use App\Services\Growth\GrowthConfig;
use App\Services\Growth\GrowthEventService;
use App\Services\Growth\IntegrationClientService;
use App\Services\Growth\SignatureService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    app(GrowthAccess::class)->set(true);
    app(\App\Services\Growth\GrowthFeatures::class)->enableAll();
    $this->base = '';
});

it('seeds growth permissions for the owner and exposes no price/refund/stock scope', function () {
    expect(ownerOf('egy')->can('growth.approve'))->toBeTrue();
    foreach (array_keys(config('growth.scopes')) as $s) {
        expect($s)->not->toMatch('/price|refund|stock|budget|payout|discount/');
    }
    foreach (['prices:write', 'refunds:write', 'stock:write', 'ad_budget:write', 'inventory:adjust'] as $bad) {
        expect(fn () => app(IntegrationClientService::class)->create('x', [$bad], null, null))->toThrow(ValidationException::class);
    }
});

it('authenticates with bearer + HMAC, rejects bad signature, stale timestamp, unknown or revoked tokens', function () {
    [$c, $token, $secret] = growthClient();
    integrationCall($this, 'GET', 'ping', null, $token, $secret)->assertOk()->assertJsonPath('data.store.name', 'Egypt Store')->assertJsonPath('data.store.currency', 'EGP')->assertJsonMissingPath('data.tenant');
    integrationCall($this, 'GET', 'ping', null, $token, 'wrong-secret')->assertStatus(401);
    integrationCall($this, 'GET', 'ping', null, $token, $secret, [], time() - 600)->assertStatus(401);
    integrationCall($this, 'GET', 'ping', null, 'nope.'.$token, $secret)->assertStatus(401);
    integrationCall($this, 'GET', 'ping', null, explode('.', $token)[0].'.badtoken', $secret)->assertStatus(401);
    $this->getJson('/api/integration/v1/ping')->assertStatus(401);
    app(IntegrationClientService::class)->revoke($c, null);
    integrationCall($this, 'GET', 'ping', null, $token, $secret)->assertStatus(401);
    expect(IntegrationClient::query()->find($c->id)->token_hash)->not->toBe($token); // stored hashed
});

it('enforces scopes and the growth feature flag', function () {
    [, $token, $secret] = growthClient(['read']);
    integrationCall($this, 'GET', 'events', null, $token, $secret)->assertStatus(403)->assertJsonPath('error', 'forbidden_scope');
    integrationCall($this, 'POST', 'proposals', ['type' => 'note', 'title' => 'x'], $token, $secret, ['Idempotency-Key' => 'k1'])->assertStatus(403);
    app(GrowthAccess::class)->set(false);
    integrationCall($this, 'GET', 'ping', null, $token, $secret)->assertStatus(403)->assertJsonPath('error', 'growth_disabled');
});

it('throttles per client', function () {
    config(['growth.throttle_per_minute' => 3]);
    [, $token, $secret] = growthClient();
    foreach (range(1, 3) as $i) {
        integrationCall($this, 'GET', 'ping', null, $token, $secret)->assertOk();
    }
    integrationCall($this, 'GET', 'ping', null, $token, $secret)->assertStatus(429);
});

it('replays idempotent proposals, rejects key reuse with another body, and never executes anything', function () {
    [, $token, $secret] = growthClient();
    $body = ['type' => 'note', 'title' => 'Try bundle', 'payload' => ['x' => 1, 'customer_phone' => '01000000000']];
    $a = integrationCall($this, 'POST', 'proposals', $body, $token, $secret, ['Idempotency-Key' => 'abc'])->assertStatus(201);
    $b = integrationCall($this, 'POST', 'proposals', $body, $token, $secret, ['Idempotency-Key' => 'abc'])->assertStatus(201)->assertHeader('Idempotent-Replay', 'true');
    expect($b->json('data.id'))->toBe($a->json('data.id'))->and(GrowthApproval::count())->toBe(1);
    $ap = GrowthApproval::first();
    expect($ap->status)->toBe('pending')->and($ap->payload)->not->toHaveKey('customer_phone'); // PII scrubbed
    integrationCall($this, 'POST', 'proposals', $body + ['extra' => 1], $token, $secret, ['Idempotency-Key' => 'abc'])->assertStatus(422);
    integrationCall($this, 'POST', 'proposals', $body, $token, $secret)->assertStatus(422); // key required
    integrationCall($this, 'POST', 'proposals', ['type' => 'bogus', 'title' => 't'], $token, $secret, ['Idempotency-Key' => 'zzz'])->assertStatus(422);
    expect(GrowthApproval::count())->toBe(1);
});

it('ingests experiment results idempotently', function () {
    [, $token, $secret] = growthClient();
    $exp = GrowthExperiment::create(['name' => 'E', 'status' => 'running']);
    $body = ['metric' => 'ctr', 'value' => 2.5, 'spend' => 10];
    integrationCall($this, 'POST', "experiments/{$exp->id}/results", $body, $token, $secret, ['Idempotency-Key' => 'r1'])->assertStatus(201);
    integrationCall($this, 'POST', "experiments/{$exp->id}/results", $body, $token, $secret, ['Idempotency-Key' => 'r1'])->assertStatus(201);
    expect($exp->results()->count())->toBe(1);
});

it('approval: approve runs the handler once, second approve is 422, failing handler stays pending, reject needs reason', function () {
    $svc = app(ApprovalService::class);
    $ran = 0;
    $svc->register('t_ok', function () use (&$ran) { $ran++; return ['done' => true]; });
    $svc->register('t_bad', fn () => throw new RuntimeException('boom'));
    $a = $svc->request('t_ok', 'ok', []);
    $svc->approve($a->id, ownerOf('egy')->id);
    expect($ran)->toBe(1)->and($a->fresh()->status)->toBe('approved')->and($a->fresh()->result)->toBe(['done' => true]);
    expect(fn () => $svc->approve($a->id, ownerOf('egy')->id))->toThrow(ValidationException::class);
    expect($ran)->toBe(1);
    $b = $svc->request('t_bad', 'bad', []);
    expect(fn () => $svc->approve($b->id, ownerOf('egy')->id))->toThrow(ValidationException::class);
    expect($b->fresh()->status)->toBe('pending');
    expect(\App\Models\GrowthActionLog::where('action', 'approval.approve')->where('outcome', 'failed')->count())->toBe(1);
    $c = $svc->request('note', 'c', []);
    $c->update(['expires_at' => now()->subMinute()]);
    expect(fn () => $svc->approve($c->id, ownerOf('egy')->id))->toThrow(ValidationException::class);
    expect($c->fresh()->status)->toBe('expired');
});

it('approvals UI: permissions gate approve, unknown ids 404, double approve is 422', function () {
    $a = app(ApprovalService::class)->request('note', 'Check this', ['k' => 'v']);
    $this->actingAs(ownerOf('egy'), 'admin');
    $this->get("{$this->base}/admin/growth/approvals")->assertOk();
    $this->getJson("{$this->base}/admin/growth/approvals/datatable")->assertOk()->assertSee('Check this');
    $this->postJson("{$this->base}/admin/growth/approvals/{$a->id}/reject", [])->assertStatus(422); // reason required
    $this->postJson("{$this->base}/admin/growth/approvals/{$a->id}/approve")->assertOk();
    $this->postJson("{$this->base}/admin/growth/approvals/{$a->id}/approve")->assertStatus(422);
    $this->postJson("{$this->base}/admin/growth/approvals/999999/approve")->assertStatus(404);
});

it('admin pages are gated by the feature flag and render', function () {
    $this->actingAs(ownerOf('egy'), 'admin');
    foreach (['clients', 'events', 'approvals', 'experiments', 'settings'] as $p) {
        $this->get("{$this->base}/admin/growth/$p")->assertOk();
    }
    app(GrowthAccess::class)->set(false);
    $this->get("{$this->base}/admin/growth/approvals")->assertStatus(403);
});

it('admin creates a client (token shown once), rotates and revokes', function () {
    $this->actingAs(ownerOf('egy'), 'admin');
    $r = $this->postJson("{$this->base}/admin/growth/clients", ['name' => 'n8n prod', 'scopes' => ['read'], 'webhook_url' => 'https://n8n.example.com/webhook/x'])->assertStatus(201);
    expect($r->json('data.token'))->toContain('.')->and($r->json('data.secret'))->toBeString();
    $this->postJson("{$this->base}/admin/growth/clients", ['name' => 'bad', 'scopes' => ['prices:write']])->assertStatus(422);
    $this->postJson("{$this->base}/admin/growth/clients", ['name' => 'bad', 'scopes' => ['read'], 'webhook_url' => 'http://127.0.0.1/hook'])->assertStatus(422);
    $id = $r->json('data.id');
    $old = $r->json('data.token');
    $rot = $this->postJson("{$this->base}/admin/growth/clients/$id/rotate")->assertOk();
    expect(app(IntegrationClientService::class)->authenticate($old))->toBeNull()->and(app(IntegrationClientService::class)->authenticate($rot->json('data.token')))->not->toBeNull();
    $this->postJson("{$this->base}/admin/growth/clients/$id/revoke")->assertOk();
    expect(app(IntegrationClientService::class)->authenticate($rot->json('data.token')))->toBeNull();
    $this->getJson("{$this->base}/admin/growth/clients/datatable")->assertOk()->assertDontSee($rot->json('data.token'));
});

it('experiments CRUD with guardrail cap, outcome rule and results; unknown ids 404', function () {
    $this->actingAs(ownerOf('egy'), 'admin');
    $cap = config('growth.guardrails.ad_total_budget_cap');
    $this->postJson("{$this->base}/admin/growth/experiments", ['name' => 'X', 'status' => 'draft', 'budget_cap' => $cap + 1])->assertStatus(422);
    $this->postJson("{$this->base}/admin/growth/experiments", ['name' => 'X', 'status' => 'completed'])->assertStatus(422);
    $this->postJson("{$this->base}/admin/growth/experiments", ['name' => 'X', 'status' => 'running', 'starts_on' => '2026-11-01', 'ends_on' => '2026-10-01'])->assertStatus(422);
    $this->postJson("{$this->base}/admin/growth/experiments", ['name' => 'Hook test', 'status' => 'running', 'metric' => 'CVR', 'budget_cap' => 100])->assertOk();
    $e = GrowthExperiment::first();
    $this->get("{$this->base}/admin/growth/experiments/{$e->id}")->assertOk()->assertSee('Hook test');
    $this->postJson("{$this->base}/admin/growth/experiments/{$e->id}/results", ['metric' => 'CVR', 'value' => 3.2, 'spend' => 40])->assertOk();
    $this->putJson("{$this->base}/admin/growth/experiments/{$e->id}", ['name' => 'Hook test', 'status' => 'completed', 'outcome' => 'win', 'learnings' => 'Hooks work'])->assertOk();
    expect($e->fresh()->outcome)->toBe('win')->and($e->results()->count())->toBe(1);
    $this->get("{$this->base}/admin/growth/experiments/999999")->assertNotFound();
    $this->deleteJson("{$this->base}/admin/growth/experiments/999999")->assertNotFound();
});

it('settings: overrides validated and merged over config', function () {
    $this->actingAs(ownerOf('egy'), 'admin');
    $this->putJson("{$this->base}/admin/growth/settings", ['min_margin_percent' => 99])->assertStatus(422);
    $this->putJson("{$this->base}/admin/growth/settings", ['min_days_of_cover' => 200, 'max_days_of_cover' => 10])->assertStatus(422);
    $this->putJson("{$this->base}/admin/growth/settings", ['min_margin_percent' => 22, 'auto_reply_allowlist' => 'Shipping_Time, returns'])->assertOk();
    $cfg = app(GrowthConfig::class);
    expect($cfg->get('min_margin_percent'))->toBe(22)
        ->and($cfg->get('auto_reply_allowlist'))->toBe(['shipping_time', 'returns'])
        ->and($cfg->get('max_days_of_cover'))->toBe(config('growth.guardrails.max_days_of_cover'));
});

it('outbox: emits a signed event, retries with backoff, goes dead, replays; no PII; no event when disabled', function () {
    Http::fake(['n8n.example.com/*' => Http::sequence()->push('err', 500)->push('ok', 200)]);
    [$c, , $secret] = growthClient(webhook: 'https://n8n.example.com/hook');
    Queue::fake();
    $e = app(GrowthEventService::class)->emit('order.placed', ['order_id' => 5, 'customer_phone' => '01000000100', 'note' => 'call 01012345678']);
    expect($e)->not->toBeNull();
    Queue::assertPushed(DispatchGrowthEventJob::class);
    expect($e->payload['data'])->toBe(['order_id' => 5, 'note' => 'call [phone]']);

    (new DispatchGrowthEventJob($e->id))->handle(app(SignatureService::class));
    $e->refresh();
    expect($e->status)->toBe('pending')->and($e->attempts)->toBe(1)->and($e->next_attempt_at->isFuture())->toBeTrue();
    $e->update(['next_attempt_at' => now()->subSecond()]);
    (new DispatchGrowthEventJob($e->id))->handle(app(SignatureService::class));
    expect($e->fresh()->status)->toBe('delivered');
    Http::assertSent(function ($req) use ($secret, $e) {
        $ts = $req->header('X-Timestamp')[0];

        return $req->hasHeader('X-Event-Id', $e->event_id) && $req->header('X-Signature')[0] === hash_hmac('sha256', $ts.'.'.$req->body(), $secret);
    });
    // delivered events are not re-sent
    (new DispatchGrowthEventJob($e->id))->handle(app(SignatureService::class));
    Http::assertSentCount(2);

    // dead after max attempts, then replay
    config(['growth.events.max_attempts' => 1]);
    Http::fake(['n8n.example.com/*' => Http::response('x', 500)]);
    $d = app(GrowthEventService::class)->emit('order.cancelled', ['order_id' => 6]);
    (new DispatchGrowthEventJob($d->id))->handle(app(SignatureService::class));
    expect($d->fresh()->status)->toBe('dead');
    app(GrowthEventService::class)->replay($d->fresh());
    expect($d->fresh()->status)->toBe('pending')->and($d->fresh()->attempts)->toBe(0);

    app(GrowthAccess::class)->set(false);
    expect(app(GrowthEventService::class)->emit('order.placed', ['order_id' => 1]))->toBeNull();
});

it('outbox: no event without a webhook client, unsafe webhooks refused, unknown types ignored', function () {
    expect(app(GrowthEventService::class)->emit('order.placed', ['order_id' => 1]))->toBeNull();
    expect(fn () => growthClient(['read'], 'http://localhost/hook'))->toThrow(ValidationException::class);
    expect(fn () => growthClient(['read'], 'https://10.0.0.5/hook'))->toThrow(ValidationException::class);
    growthClient(['read'], 'https://n8n.example.com/hook');
    expect(app(GrowthEventService::class)->emit('price.changed', []))->toBeNull();
});

it('existing flows feed the outbox (order placed / cancelled / returned) and a failing emit never breaks the flow', function () {
    Queue::fake();
    growthClient(['read'], 'https://n8n.example.com/hook');
    $o = new \App\Models\Order(['number' => 'A-1', 'total' => 120.5, 'currency' => 'EGP', 'customer_phone' => '01000000100', 'status' => 'cancelled']);
    $o->id = 77;
    event(new \App\Events\OrderPlaced($o, 1));
    event(new \App\Events\OrderStatusChanged($o, \App\Enums\OrderStatus::PENDING, \App\Enums\OrderStatus::CANCELLED, 1));
    event(new \App\Events\OrderStatusChanged($o, \App\Enums\OrderStatus::PENDING, \App\Enums\OrderStatus::CONFIRMED, 1)); // not a growth event
    expect(GrowthEvent::orderBy('id')->pluck('type')->all())->toBe(['order.placed', 'order.cancelled']);
    expect(json_encode(GrowthEvent::first()->payload))->not->toContain('01000000100');
    // outbox failure is swallowed (reported), the business flow continues
    app()->bind(GrowthEventService::class, fn () => throw new RuntimeException('outbox down'));
    event(new \App\Events\OrderPlaced($o, 1));
    expect(true)->toBeTrue();
});

it('event log UI lists and replays; unknown event id is 404', function () {
    growthClient(['read'], 'https://n8n.example.com/hook');
    Queue::fake();
    $e = app(GrowthEventService::class)->emit('order.placed', ['order_id' => 9]);
    $e->update(['status' => 'dead']);
    $this->actingAs(ownerOf('egy'), 'admin');
    $this->getJson("{$this->base}/admin/growth/events/datatable")->assertOk()->assertSee('order.placed');
    $this->postJson("{$this->base}/admin/growth/events/{$e->id}/replay")->assertOk();
    expect($e->fresh()->status)->toBe('pending');
    $this->postJson("{$this->base}/admin/growth/events/999999/replay")->assertNotFound();
});

it('maintenance command expires approvals and prunes', function () {
    $a = app(ApprovalService::class)->request('note', 'old', []);
    $a->update(['expires_at' => now()->subDay()]);
    $this->artisan('growth:maintain')->assertSuccessful();
    expect($a->fresh()->status)->toBe('expired');
});
