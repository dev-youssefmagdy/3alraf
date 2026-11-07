<?php

// N8 adversarial review: regression tests for everything the QA pass found plus systematic sweeps (switches, lang parity).

use App\Models\CeoChallenge;
use App\Models\GrowthApproval;
use App\Models\Admin;
use App\Services\Growth\ApprovalService;
use App\Services\Growth\CeoChallengeService;
use App\Services\Growth\GrowthAccess;
use App\Services\Growth\GrowthFeatures;
use App\Services\Growth\IntegrationClientService;
use App\Services\Growth\SignatureService;
use App\Services\Growth\TrendRadarService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    app(GrowthAccess::class)->set(true);
    app(GrowthFeatures::class)->enableAll();
    $this->setAll = function (bool $on, array $except = []) {
        foreach (array_keys(config('growth.features')) as $k) {
            growthFeatures($on || in_array($k, $except, true), $k);
        }
    };
});

function rvClient(array $scopes): array
{
    $r = app(IntegrationClientService::class)->create('n8n', $scopes, null, null);

    return $r;
}

function rvCall($test, string $method, string $path, ?array $body, array $c, array $headers = [], ?int $ts = null)
{
    [, $token, $secret] = $c;
    $raw = $body === null ? '' : json_encode($body);
    $ts ??= time();
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_X_TIMESTAMP' => (string) $ts, 'HTTP_X_SIGNATURE' => app(SignatureService::class)->sign($secret, $ts, $raw), 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'] + $headers;

    return $test->call($method, '/api/integration/v1/'.ltrim($path, '/'), [], [], [], $server, $raw);
}

function rvIntegrationRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/integration/v1/'))->values()->all();
}

it('has no duplicate method+uri or route names in the integration API (returns/reasons N1 vs N6)', function () {
    $seen = [];
    $names = [];
    foreach (app('router')->getRoutes()->getRoutes() as $r) {
        if (! str_starts_with($r->uri(), 'api/integration/v1/')) {
            continue;
        }
        foreach ($r->methods() as $m) {
            if ($m === 'HEAD') {
                continue;
            }
            expect($seen)->not->toHaveKey($m.' '.$r->uri());
            $seen[$m.' '.$r->uri()] = true;
        }
        expect($names)->not->toHaveKey((string) $r->getName());
        $names[(string) $r->getName()] = true;
    }
    expect(count($seen))->toBeGreaterThan(30);
});

it('serves the return-reasons report only behind the return_intel switch', function () {
    $c = rvClient(['read']);
    rvCall($this, 'GET', 'returns/reasons', null, $c)->assertOk()->assertJsonStructure(['data' => ['aggregate']]);
    ($this->setAll)(true, []);
    ($this->setAll)(false, ['integration_api']);
    rvCall($this, 'GET', 'returns/reasons', null, $c)->assertStatus(403)->assertJsonPath('error', 'feature_disabled');
});

it('with every feature switch OFF, every admin growth route is 404 (web) or 403 (json) except the settings page', function () {
    ($this->setAll)(false);
    $admin = Admin::query()->where('email', 'owner@egy.test')->first();
    $n = 0;
    foreach (app('router')->getRoutes()->getRoutes() as $r) {
        $name = (string) $r->getName();
        if (! str_starts_with($name, 'admin.growth.') || str_starts_with($name, 'admin.growth.settings.')) {
            continue;
        }
        $uri = preg_replace('/\{[^}]+\}/', '1', $r->uri());
        $method = collect($r->methods())->first(fn ($m) => $m !== 'HEAD');
        $web = $this->actingAs($admin, 'admin')->call($method, '/'.$uri);
        expect($web->getStatusCode())->toBe(404, "$method $uri (web)");
        $json = $this->actingAs($admin, 'admin')->call($method, '/'.$uri, [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        expect($json->getStatusCode())->toBe(403, "$method $uri (json)");
        $n++;
    }
    expect($n)->toBeGreaterThan(60);
});

it('with the master flag OFF every integration route is denied and with all switches OFF every route is denied', function () {
    $c = rvClient(['read', 'ingest:write', 'events:read', 'proposals:write']);
    ($this->setAll)(false);
    foreach (rvIntegrationRoutes() as $r) {
        $method = collect($r->methods())->first(fn ($m) => $m !== 'HEAD');
        $uri = preg_replace('/\{[^}]+\}/', '1', substr($r->uri(), strlen('api/integration/v1/')));
        expect(rvCall($this, $method, $uri, $method === 'POST' ? [] : null, $c, ['HTTP_IDEMPOTENCY_KEY' => 'k'])->getStatusCode())->toBe(403, "$method $uri all OFF");
    }
    ($this->setAll)(true);
    app(GrowthAccess::class)->set(false);
    foreach (rvIntegrationRoutes() as $r) {
        $method = collect($r->methods())->first(fn ($m) => $m !== 'HEAD');
        $uri = preg_replace('/\{[^}]+\}/', '1', substr($r->uri(), strlen('api/integration/v1/')));
        expect(rvCall($this, $method, $uri, $method === 'POST' ? [] : null, $c, ['HTTP_IDEMPOTENCY_KEY' => 'k'])->getStatusCode())->toBe(403, "$method $uri master OFF");
    }
});

it('every feature-bound integration route is denied when only integration_api is ON', function () {
    $c = rvClient(['read', 'ingest:write', 'events:read', 'proposals:write']);
    ($this->setAll)(false, ['integration_api']);
    $bound = 0;
    foreach (rvIntegrationRoutes() as $r) {
        $mw = collect($r->gatherMiddleware())->first(fn ($m) => is_string($m) && str_starts_with($m, 'integration.auth'));
        $parts = explode(',', (string) preg_replace('/^integration\.auth:?/', '', $mw), 2);
        if (empty($parts[1])) {
            continue;
        }
        $bound++;
        $method = collect($r->methods())->first(fn ($m) => $m !== 'HEAD');
        $uri = preg_replace('/\{[^}]+\}/', '1', substr($r->uri(), strlen('api/integration/v1/')));
        expect(rvCall($this, $method, $uri, $method === 'POST' ? [] : null, $c, ['HTTP_IDEMPOTENCY_KEY' => 'k'])->getStatusCode())->toBe(403, "$method $uri ({$parts[1]} OFF)");
    }
    expect($bound)->toBeGreaterThan(20);
});

it('scheduled growth commands are no-ops with every switch OFF', function () {
    ($this->setAll)(false);
    $tables = ['growth_events', 'growth_daily_stats', 'trend_candidates', 'ceo_challenges', 'improvement_tasks', 'reputation_alerts', 'missed_opportunities', 'growth_approvals'];
    $count = fn () => collect($tables)->map(fn ($t) => DB::table($t)->count())->sum();
    $before = $count();
    $snap = fn () => collect($tables)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();
    $s0 = $snap();
    foreach (['growth:engage', 'growth:operate', 'growth:scan-low-stock', 'growth:aggregate', 'growth:dispatch-pending'] as $cmd) {
        $this->artisan($cmd)->assertSuccessful();
    }
    expect($snap())->toBe($s0);
});

it('a failing growth job step fails growth:operate loudly (no silent per-store catch any more)', function () {
    $this->mock(TrendRadarService::class, function ($m) {
        $m->shouldReceive('scan')->andThrow(new RuntimeException('boom'));
    });
    expect(fn () => $this->artisan('growth:operate')->run())->toThrow(RuntimeException::class, 'boom');
});

it('does not let n8n propose approval types that execute internal handlers (only whitelisted types)', function () {
    $c = rvClient(['proposals:write']);
    foreach (['inventory_reorder', 'bundle_draft', 'batch_reorder', 'experiment_budget'] as $type) {
        rvCall($this, 'POST', 'proposals', ['type' => $type, 'title' => 'x', 'payload' => ['qty' => 99999]], $c, ['HTTP_IDEMPOTENCY_KEY' => 'k-'.$type])->assertStatus(422);
    }
    expect(GrowthApproval::count())->toBe(0);
    rvCall($this, 'POST', 'proposals', ['type' => 'note', 'title' => 'hello'], $c, ['HTTP_IDEMPOTENCY_KEY' => 'k-note'])->assertStatus(201);
    expect(GrowthApproval::count())->toBe(1);
});

it('rejects stale and future HMAC timestamps and replays the stored response for a repeated idempotent POST', function () {
    $c = rvClient(['proposals:write']);
    rvCall($this, 'GET', 'ping', null, $c, [], time() - 3600)->assertStatus(401);
    rvCall($this, 'GET', 'ping', null, $c, [], time() + 3600)->assertStatus(401);
    $b = ['type' => 'note', 'title' => 'once'];
    rvCall($this, 'POST', 'proposals', $b, $c, ['HTTP_IDEMPOTENCY_KEY' => 'same'])->assertStatus(201);
    rvCall($this, 'POST', 'proposals', $b, $c, ['HTTP_IDEMPOTENCY_KEY' => 'same'])->assertStatus(201)->assertHeader('Idempotent-Replay', 'true');
    rvCall($this, 'POST', 'proposals', ['type' => 'note', 'title' => 'other'], $c, ['HTTP_IDEMPOTENCY_KEY' => 'same'])->assertStatus(422);
    expect(GrowthApproval::count())->toBe(1);
});

it('does not double-execute an approval on a second approve', function () {
    $svc = app(ApprovalService::class);
    $a = $svc->request('note', 'x', []);
    $admin = Admin::query()->where('email', 'owner@egy.test')->value('id');
    $svc->approve($a->id, $admin);
    expect(fn () => $svc->approve($a->id, $admin))->toThrow(ValidationException::class);
    expect(fn () => $svc->reject($a->id, $admin, 'late'))->toThrow(ValidationException::class);
});

it('has no integration route that can change price, refund, stock or budget', function () {
    foreach (rvIntegrationRoutes() as $r) {
        expect($r->uri())->not->toMatch('/price|refund|stock\/adjust|budget|payout|purchase|discount|order.*status/i');
        expect($r->methods())->not->toContain('PUT')->not->toContain('PATCH')->not->toContain('DELETE');
    }
});

it('keeps AR and EN growth lang files in key parity with no empty values', function () {
    $flat = function (array $a, string $p = '') use (&$flat) {
        $o = [];
        foreach ($a as $k => $v) {
            is_array($v) ? $o += $flat($v, $p.$k.'.') : $o[$p.$k] = $v;
        }

        return $o;
    };
    foreach (glob(lang_path('en/growth*.php')) as $f) {
        $name = basename($f);
        expect(file_exists(lang_path('ar/'.$name)))->toBeTrue("ar/$name missing");
        $en = $flat(require $f);
        $ar = $flat(require lang_path('ar/'.$name));
        expect(array_values(array_diff(array_keys($en), array_keys($ar))))->toBe([], "$name keys missing in ar");
        expect(array_values(array_diff(array_keys($ar), array_keys($en))))->toBe([], "$name keys missing in en");
        foreach ($ar as $k => $v) {
            expect(trim((string) $v))->not->toBe('', "$name $k empty in ar");
        }
    }
    foreach (glob(lang_path('ar/growth*.php')) as $f) {
        expect(file_exists(lang_path('en/'.basename($f))))->toBeTrue();
    }
});
