<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Growth\GrowthAccess;
use App\Services\Growth\GrowthFeatures;
use App\Services\Growth\IntegrationClientService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

/**
 * Offline replay of the n8n workflow pack. Every "Sign ..." Code node of docs/n8n/workflows/*.json is executed by node exactly as n8n would run it
 * (docs/n8n/selftest-runner.cjs: same JS, same HMAC), and the HTTP request it builds is sent through the REAL kernel/routes with the named credential,
 * once with every Growth feature ON and once with them OFF. It proves routes, HMAC, scopes and feature gating match the workflows. It does NOT prove n8n itself
 * (node wiring, credentials UI, schedules): use the checklist in docs/n8n/README.md for that. Everything runs inside a DB transaction that is rolled back.
 */
class GrowthN8nSelftest extends Command
{
    protected $signature = 'growth:n8n-selftest {--tenant=egy : tenant slug (all changes are rolled back)} {--json : machine readable result}';

    protected $description = 'Replay every HTTP node of the n8n workflow pack against the real integration routes (ON/OFF) and assert status/shape. Rolled back, never touches data.';

    public function handle(GrowthFeatures $features, GrowthAccess $access, IntegrationClientService $clients): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }
        $tenant = Tenant::where('slug', $this->option('tenant'))->first();
        if (! $tenant) {
            $this->error('Tenant not found');

            return self::FAILURE;
        }
        $prev = app()->bound('currentTenant') ? app('currentTenant') : null;
        $problems = [];
        $stats = ['requests' => 0, 'ok' => 0];
        DB::beginTransaction();
        try {
            $access->set($tenant, true);
            foreach (['on', 'off'] as $mode) {
                Cache::flush();
                app()->instance('currentTenant', $tenant->refresh());
                $mode === 'on' ? $features->enableAll($tenant) : $features->update($tenant, array_merge(array_fill_keys(array_keys($features->registry()), false), ['integration_api' => true]));
                app()->instance('currentTenant', $tenant->refresh());
                [, $token, $secret] = $clients->create('selftest-'.$mode, array_keys(config('growth.scopes')), null, null);
                $base = 'http://'.config('app.central_domain').'/api/integration/v1';
                $reqs = $this->build($base, $secret);
                if ($reqs === null) {
                    return self::FAILURE;
                }
                foreach ($reqs as $r) {
                    $stats['requests']++;
                    $p = $this->check($r, $mode, $base, $token);
                    $p ? $problems[] = $p : $stats['ok']++;
                }
            }
        } finally {
            DB::rollBack();
            Cache::flush();
            $prev ? app()->instance('currentTenant', $prev) : app()->forgetInstance('currentTenant');
        }
        if ($this->option('json')) {
            $this->line(json_encode(['stats' => $stats, 'problems' => $problems]));
        } else {
            $this->info("Replayed {$stats['requests']} request(s) (ON + OFF): {$stats['ok']} as expected, ".count($problems).' problem(s).');
            foreach ($problems as $p) {
                $this->error($p);
            }
            $this->line('Not covered: n8n itself (wiring, credential UI, schedules, webhooks). See docs/n8n/README.md dry-run checklist.');
        }

        return $problems ? self::FAILURE : self::SUCCESS;
    }

    private function build(string $base, string $secret): ?array
    {
        $p = new Process(['node', base_path('docs/n8n/selftest-runner.cjs'), base_path('docs/n8n/workflows')], null, ['GROWTH_API_BASE' => $base, 'GROWTH_HMAC_SECRET' => $secret]);
        $p->run();
        if (! $p->isSuccessful()) {
            $this->error('node runner failed (is node >= 18 installed?): '.trim($p->getErrorOutput()));

            return null;
        }

        return json_decode($p->getOutput(), true) ?: [];
    }

    /** @return string|null problem description */
    private function check(array $r, string $mode, string $base, string $token): ?string
    {
        $label = "{$r['workflow']} / {$r['node']}";
        if (isset($r['error'])) {
            return "$label: node could not be evaluated ({$r['error']})";
        }
        $request = Request::create($r['url'], $r['method'], [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json']
            + collect($r['headers'])->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])->all(), (string) $r['body']);
        $route = Route::getRoutes()->match($request);
        $mw = collect($route->gatherMiddleware())->first(fn ($m) => is_string($m) && str_starts_with($m, 'integration.auth'));
        $args = $mw && str_contains($mw, ':') ? explode(',', substr($mw, strpos($mw, ':') + 1)) : [];
        $feature = $args[1] ?? '';
        if (! str_starts_with((string) $r['url'], $base)) {
            return "$label: URL does not start with the API base ({$r['url']})";
        }
        $app = app(Kernel::class);
        $res = $app->handle($request);
        $status = $res->getStatusCode();
        $body = json_decode($res->getContent(), true);
        $where = "$label [$mode] {$r['method']} ".substr($r['url'], strlen($base));
        if ($status === 401) {
            return "$where: HMAC/bearer rejected (401)";
        }
        if (in_array($status, [405, 500, 429], true) || (is_array($body) && str_contains((string) ($body['message'] ?? ''), 'could not be found') && $status === 404)) {
            return "$where: unexpected status $status ".mb_substr((string) (is_array($body) ? ($body['message'] ?? '') : ''), 0, 160);
        }
        if ($mode === 'off' && $feature !== '') {
            return $status === 403 && ($body['error'] ?? '') === 'feature_disabled' ? null : "$where: feature '$feature' is OFF but got $status (expected 403 feature_disabled)";
        }
        if ($status === 403) {
            return "$where: forbidden ({$body['error']}) with the feature ON";
        }
        if (! is_array($body)) {
            return "$where: unexpected response shape (status $status)";
        }

        return null; // 2xx/422/404-model: authenticated, routed, gated correctly
    }
}
