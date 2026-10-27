<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PingController extends Controller
{
    public function __invoke(Request $r): JsonResponse
    {
        $c = $r->attributes->get('integration_client');

        return response()->json(['success' => true, 'data' => ['tenant' => current_tenant()->slug, 'client' => $c->name, 'scopes' => $c->scopes, 'server_time' => now()->toIso8601String()]]);
    }
}
