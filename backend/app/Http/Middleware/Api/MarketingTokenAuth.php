<?php

namespace App\Http\Middleware\Api;

use App\Models\MarketingApiRequest;
use App\Models\MarketingApiToken;
use App\Services\Marketing\MarketingTokenService;
use App\Support\Marketing\MarketingResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route gate `marketing.token:{scope}` for /api/internal/marketing (P4): Bearer service token (MarketingApiToken), constant-time hash compare,
 * revoked / expired ⇒ 401 (uniform, so tokens cannot be probed), missing scope ⇒ 403, per-token throttle (config marketing.throttle_per_minute) ⇒ 429.
 * Every call of a known token is logged in marketing_api_requests (token, method, route name, status, duration ms) — no body, no IP, no PII.
 * The resolved token is available as $request->attributes->get('marketing_token').
 */
class MarketingTokenAuth
{
    public function __construct(private MarketingTokenService $tokens) {}

    public function handle(Request $request, Closure $next, string $scope = 'marketing:read'): Response
    {
        $started = microtime(true);
        Auth::forgetGuards();   // never a session user on this API
        $bearer = (string) $request->bearerToken();
        $token = $bearer !== '' ? $this->tokens->resolve($bearer) : null;

        if (! $token || ! $token->isUsable()) {
            $res = MarketingResponse::error(401, 'unauthenticated');

            return $token ? $this->log($request, $token, $res, $started) : $res;
        }
        if (! $token->hasScope($scope)) {
            return $this->log($request, $token, MarketingResponse::error(403, 'forbidden_scope'), $started);
        }
        $limit = max(1, (int) config('marketing.throttle_per_minute', 120));
        if (RateLimiter::tooManyAttempts($key = 'marketing-api|'.$token->id, $limit)) {
            $res = MarketingResponse::error(429, 'too_many_requests');
            $res->headers->set('Retry-After', (string) RateLimiter::availableIn($key));

            return $this->log($request, $token, $res, $started);
        }
        RateLimiter::hit($key, 60);

        $request->attributes->set('marketing_token', $token);
        $this->tokens->touch($token);

        return $this->log($request, $token, $next($request), $started);
    }

    private function log(Request $request, MarketingApiToken $token, Response $response, float $started): Response
    {
        try {
            MarketingApiRequest::create([
                'token_id' => $token->id, 'method' => mb_substr($request->method(), 0, 8), 'route' => mb_substr((string) ($request->route()?->getName() ?? ''), 0, 120) ?: null,
                'status' => $response->getStatusCode(), 'duration_ms' => (int) round((microtime(true) - $started) * 1000), 'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e); // logging never breaks a call
        }

        return $response;
    }
}
