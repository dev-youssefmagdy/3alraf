<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Services\GiftFinder\GiftFinderSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** GET /api/integration/v1/health: monitoring probe for n8n (auth works, database reachable, Gift Finder switch). */
class HealthController extends Controller
{
    public function __invoke(GiftFinderSettings $gift): JsonResponse
    {
        $db = true;
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $db = false;
        }

        return response()->json(['success' => true, 'data' => ['status' => $db ? 'ok' : 'degraded', 'database' => $db, 'gift_finder_enabled' => $gift->enabled(), 'server_time' => now()->toIso8601String()]]);
    }
}
