<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /api/integration/v1/ping: credentials + signature check for n8n (who am I, which scopes, server clock for the replay window). */
class PingController extends Controller
{
    public function __invoke(Request $r): JsonResponse
    {
        $c = $r->attributes->get('integration_client');

        return response()->json(['success' => true, 'data' => ['store' => ['name' => setting('name'), 'url' => url('/'), 'currency' => setting('currency_code'), 'timezone' => setting('timezone')],
            'client' => $c->name, 'scopes' => $c->scopes, 'server_time' => now()->toIso8601String()]]);
    }
}
