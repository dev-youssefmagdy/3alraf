<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** `GET {central}/api/v1/health` - liveness + dependency check for load balancers / uptime monitors. No secrets, no tenant data. */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->probe(fn () => DB::select('select 1') !== []),
            'cache' => $this->probe(function () {
                Cache::put('health.ping', '1', 10);

                return Cache::get('health.ping') === '1';
            }),
            'storage' => $this->probe(fn () => is_writable(storage_path('logs')) && is_writable(storage_path('framework'))),
            // queue backlog / failures are reported, not fatal (workers may be restarting)
            'failed_jobs' => $this->probe(fn () => DB::table('failed_jobs')->count() < 100),
        ];
        $ok = ! in_array(false, array_diff_key($checks, ['failed_jobs' => 1]), true);

        return response()->json(['status' => $ok ? 'ok' : 'degraded', 'checks' => array_map(fn ($v) => $v ? 'ok' : 'fail', $checks), 'time' => now()->toIso8601String()], $ok ? 200 : 503);
    }

    private function probe(\Closure $fn): bool
    {
        try {
            return (bool) $fn();
        } catch (\Throwable) {
            return false;
        }
    }
}
