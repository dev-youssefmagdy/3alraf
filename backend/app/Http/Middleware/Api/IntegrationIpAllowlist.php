<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/** Optional IP/CIDR allowlist (config growth.ip_allowlist) for the integration prefix + hardening headers on every response. */
class IntegrationIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        $list = (array) config('growth.ip_allowlist', []);
        if ($list && ! IpUtils::checkIp((string) $request->ip(), $list)) {
            return response()->json(['success' => false, 'error' => 'ip_not_allowed', 'message' => 'Forbidden'], 403);
        }
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
