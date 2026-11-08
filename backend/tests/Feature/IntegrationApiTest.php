<?php

// Read-only n8n integration API (P2): /api/integration/v1/{ping,health,gift-finder/*}, bearer + HMAC auth, scopes, call log, admin client management.

use App\Enums\StockMovementType as T;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\Bundle;
use App\Models\GiftFinderSession;
use App\Models\IntegrationClient;
use App\Models\IntegrationRequest;
use App\Models\Product;
use App\Services\BarcodeService;
use App\Services\GiftFinder\GiftFinderInsights;
use App\Services\Integration\IntegrationClientService;
use App\Services\Integration\SignatureService;
use App\Services\Stock\StockLedger;
use Carbon\CarbonImmutable;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    $this->client = fn (array $scopes = ['read']) => app(IntegrationClientService::class)->create('n8n', $scopes);
    // Signed GET exactly as n8n must send it: X-Signature = hex HMAC-SHA256(secret, "{X-Timestamp}.{raw body}"), body '' for GET.
    $this->call = function (string $path, ?string $token, ?string $secret, ?int $ts = null, ?string $sig = null) {
        $ts ??= time();
        $h = ['Accept' => 'application/json', 'X-Timestamp' => (string) $ts];
        $token && $h['Authorization'] = 'Bearer '.$token;
        $secret && $h['X-Signature'] = $sig ?? hash_hmac('sha256', $ts.'.', $secret);

        return $this->withHeaders($h)->get('/api/integration/v1/'.$path);
    };
});

it('authenticates with bearer + HMAC: 401 without / with a bad signature, stale timestamp or revoked client; 200 with', function () {
    [$c, $token, $secret] = ($this->client)();
    ($this->call)('ping', null, null)->assertUnauthorized()->assertJsonPath('error', 'unauthenticated');
    ($this->call)('ping', $token, null)->assertUnauthorized();
    ($this->call)('ping', $token, $secret, sig: str_repeat('a', 64))->assertUnauthorized();
    ($this->call)('ping', $token, 'wrong-secret')->assertUnauthorized();
    ($this->call)('ping', $token, $secret, time() - 600)->assertUnauthorized();          // outside the 300 s replay window
    ($this->call)('ping', 'nope.'.substr($token, 11), $secret)->assertUnauthorized();
    ($this->call)('ping', $token, $secret)->assertOk()->assertJsonPath('data.client', 'n8n')->assertJsonPath('data.scopes', ['read']);
    expect(app(SignatureService::class)->sign($secret, 123, ''))->toBe(hash_hmac('sha256', '123.', $secret));
    ($this->call)('health', $token, $secret)->assertOk()->assertJsonPath('data.status', 'ok')->assertJsonPath('data.gift_finder_enabled', true);
    expect(IntegrationRequest::where('integration_client_id', $c->id)->where('status_code', 200)->count())->toBe(2)
        ->and(IntegrationClient::find($c->id)->last_used_at)->not->toBeNull();

    app(IntegrationClientService::class)->revoke($c);
    ($this->call)('ping', $token, $secret)->assertUnauthorized();
});

it('gift-finder endpoints need scope read or gift_finder:read (403 otherwise) and return stats / untagged / occasions', function () {
    [, $token, $secret] = ($this->client)(['gift_finder:read']);
    $branch = Branch::first();
    $mk = function (string $slug, array $tags = []) use ($branch) {
        $p = Product::create(['type' => 'simple', 'name' => ['en' => $slug, 'ar' => $slug], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(BarcodeService::class)->generateUnique()]);
        listProduct($p, ['price' => 100, 'cost' => 40, 'is_active' => true] + $tags);
        app(StockLedger::class)->record($branch->id, $p->id, 0, T::PURCHASE_IN, 5, '40');

        return $p;
    };
    $tagged = $mk('tagged', ['gift_suitable' => true, 'gift_recipients' => ['mother']]);
    $untagged = $mk('untagged');
    $b1 = Bundle::create(['name' => ['en' => 'B1', 'ar' => 'ب1'], 'slug' => 'b1', 'price' => 150, 'is_active' => true]);
    $b1->items()->create(['product_id' => $untagged->id, 'quantity' => 1]);
    $b2 = Bundle::create(['name' => ['en' => 'B2', 'ar' => 'ب2'], 'slug' => 'b2', 'price' => 150, 'is_active' => true]);
    $b2->items()->create(['product_id' => $tagged->id, 'quantity' => 1]);
    GiftFinderSession::create(['token' => 'tok1', 'budget' => 200, 'occasion' => 'birthday', 'results_count' => 2, 'result_ids' => [1, 2]]);

    $s = ($this->call)('gift-finder/stats?days=7', $token, $secret)->assertOk()->json('data');
    expect($s['days'])->toBe(7)->and($s['sessions'])->toBe(1)->and($s['enabled'])->toBeTrue()->and($s['by_occasion'])->toBe(['birthday' => 1]);
    ($this->call)('gift-finder/stats?days=0', $token, $secret)->assertStatus(422);
    ($this->call)('gift-finder/stats?days=abc', $token, $secret)->assertStatus(422);

    $u = ($this->call)('gift-finder/untagged', $token, $secret)->assertOk()->json('data');
    $ids = collect($u['products'])->pluck('id');
    expect($ids)->toContain($untagged->id)->not->toContain($tagged->id)
        ->and(collect($u['bundles'])->pluck('slug')->all())->toBe(['b1'])->and($u['counts']['bundles'])->toBe(1);

    $o = ($this->call)('gift-finder/occasions?days=366', $token, $secret)->assertOk()->json('data');
    expect(collect($o['occasions'])->pluck('key')->unique()->sort()->values()->all())->toBe(['eid_adha', 'eid_fitr', 'mothers_day', 'ramadan', 'valentine'])
        ->and(collect($o['occasions'])->firstWhere('key', 'valentine')['approximate'])->toBeFalse()
        ->and(collect($o['occasions'])->firstWhere('key', 'ramadan')['approximate'])->toBeTrue()
        ->and(collect($o['occasions'])->firstWhere('key', 'valentine')['label'])->toBe(['ar' => 'عيد الحب', 'en' => 'Valentine']);

    // a client without a gift-finder scope (none can exist via the service, so forge one) is refused with 403
    $c = IntegrationClient::where('token_prefix', explode('.', $token)[0])->first();
    $c->forceFill(['scopes' => ['other']])->save();
    ($this->call)('gift-finder/stats', $token, $secret)->assertForbidden()->assertJsonPath('error', 'forbidden_scope');
    ($this->call)('ping', $token, $secret)->assertOk();   // ping / health need no scope
});

it('computes upcoming occasions: fixed Gregorian dates and approximate Umm al-Qura Hijri ones', function () {
    $list = app(GiftFinderInsights::class)->upcomingOccasions(40, CarbonImmutable::parse('2027-02-01'));
    $by = collect($list)->keyBy('key');
    expect($by['valentine']['date'])->toBe('2027-02-14')->and($by['valentine']['days_left'])->toBe(13)
        ->and($by['ramadan']['date'])->toBe('2027-02-08')->and($by['ramadan']['approximate'])->toBeTrue()
        ->and($by)->not->toHaveKey('eid_adha')                                  // May, outside 40 days
        ->and(collect($list)->pluck('date')->all())->toBe(collect($list)->pluck('date')->sort()->values()->all());
});

it('gift-finder/stats always returns by_occasion as a JSON object, also when there are no sessions (n8n weekly report)', function () {
    [, $token, $secret] = ($this->client)(['gift_finder:read']);
    $empty = ($this->call)('gift-finder/stats?days=7', $token, $secret)->assertOk();
    expect($empty->getContent())->toContain('"by_occasion":{}')->and($empty->json('data.sessions'))->toBe(0);

    GiftFinderSession::create(['token' => 'tok-n8n', 'occasion' => 'birthday', 'recipient' => 'friend', 'budget' => 500, 'results_count' => 3]);
    expect(($this->call)('gift-finder/stats?days=7', $token, $secret)->assertOk()->json('data.by_occasion'))->toBe(['birthday' => 1]);
});

it('the removed integration endpoints are gone (404)', function () {
    [, $token, $secret] = ($this->client)();
    foreach (['events', 'products', 'metrics/summary', 'brief/daily', 'features', 'brand-memory', 'voc', 'inventory/recommendations', 'ceo-challenge'] as $p) {
        ($this->call)($p, $token, $secret)->assertNotFound();
    }
    $this->withHeaders(['Accept' => 'application/json'])->post('/api/integration/v1/proposals', [])->assertNotFound();
    $this->withHeaders(['Accept' => 'application/json'])->post('/api/integration/v1/interactions', [])->assertNotFound();
});

it('admin manages clients under marketer.integration-clients.* (create shows secrets once, rotate, revoke, scopes validated)', function () {
    $as = fn () => $this->actingAs(Admin::query()->where('email', 'owner@egy.test')->first(), 'admin');   // IntegrationAuth forgets guards on each API call
    $as();
    $this->get('/marketer/integration-clients')->assertOk()->assertSee('/api/integration/v1');
    $r = $this->postJson('/marketer/integration-clients', ['name' => 'n8n', 'scopes' => ['gift_finder:read']])->assertCreated()->json('data');
    expect($r['token'])->toContain('.')->and(strlen($r['secret']))->toBe(48);
    $this->postJson('/marketer/integration-clients', ['name' => 'x', 'scopes' => ['proposals:write']])->assertStatus(422);
    $this->getJson('/marketer/integration-clients/datatable?draw=1&start=0&length=10')->assertOk()->assertJsonPath('recordsTotal', 1);
    ($this->call)('ping', $r['token'], $r['secret'])->assertOk();
    $rot = $as()->postJson('/marketer/integration-clients/'.$r['id'].'/rotate')->assertOk()->json('data');
    ($this->call)('ping', $r['token'], $r['secret'])->assertUnauthorized();
    ($this->call)('ping', $rot['token'], $rot['secret'])->assertOk();
    $as()->putJson('/marketer/integration-clients/'.$r['id'], ['name' => 'n8n-2', 'scopes' => ['read']])->assertOk();
    $this->postJson('/marketer/integration-clients/'.$r['id'].'/revoke')->assertOk();
    ($this->call)('ping', $rot['token'], $rot['secret'])->assertUnauthorized();
});
