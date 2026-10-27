<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Models\GrowthEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Monitoring probe for n8n: auth works, DB reachable, outbox backlog (own tenant only). */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $db = true;
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $db = false;
        }
        $pending = $db ? GrowthEvent::where('status', 'pending')->count() : null;
        $dead = $db ? GrowthEvent::where('status', 'dead')->count() : null;
        $oldest = $db ? GrowthEvent::where('status', 'pending')->min('created_at') : null;
        $ok = $db && ($dead ?? 0) === 0;

        return response()->json(['success' => true, 'data' => ['status' => $ok ? 'ok' : 'degraded', 'database' => $db, 'outbox_pending' => $pending, 'outbox_dead' => $dead, 'oldest_pending_at' => $oldest, 'server_time' => now()->toIso8601String()]]);
    }
}
