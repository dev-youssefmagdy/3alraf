<?php

namespace App\Http\Middleware\Api;

use App\Services\Growth\GrowthAccess;
use App\Services\Growth\GrowthFeatures;
use App\Services\Growth\IntegrationClientService;
use App\Services\Growth\SignatureService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route gate `integration.auth:{scope}` for the n8n API: bearer token + HMAC-SHA256(secret, "{X-Timestamp}.{raw body}"), 5-minute replay window,
 * scope check, per-client throttle. Failures are uniform (401) so tokens/clients cannot be probed. The site timezone is set once at boot (AppServiceProvider).
 */
class IntegrationAuth
{
    public function __construct(private IntegrationClientService $clients, private SignatureService $sig, private GrowthAccess $access, private GrowthFeatures $features) {}

    public function handle(Request $request, Closure $next, string $scope = '', string $feature = ''): Response
    {
        Auth::forgetGuards();
        $bearer = (string) $request->bearerToken();
        $client = $bearer !== '' ? $this->clients->authenticate($bearer) : null;
        $ts = (string) $request->header(config('growth.hmac.timestamp_header'));
        $sigHeader = (string) $request->header(config('growth.hmac.header'));

        if (! $client || ! $this->sig->withinWindow($ts) || $sigHeader === '' || ! $this->sig->verify($client->hmac_secret, $ts, $request->getContent(), $sigHeader)) {
            return $this->deny(401, 'unauthenticated');
        }
        if (! $this->access->enabled()) {
            return $this->deny(403, 'growth_disabled');
        }
        if (! $this->features->active('integration_api') || ($feature !== '' && ! $this->features->active($feature))) {
            return $this->deny(403, 'feature_disabled');
        }
        if ($scope !== '' && ! $client->hasScope($scope)) {
            return $this->deny(403, 'forbidden_scope');
        }
        $limit = (int) config('growth.throttle_per_minute');
        if (RateLimiter::tooManyAttempts($key = 'integration|'.$client->id, $limit)) {
            return $this->deny(429, 'too_many_requests')->header('Retry-After', (string) RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);

        $request->attributes->set('integration_client', $client);
        if (! $client->last_used_at || $client->last_used_at->lt(now()->subMinute())) {
            $client->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $next($request);
    }

    private function deny(int $status, string $code)
    {
        return response()->json(['success' => false, 'error' => $code, 'message' => __('growth_hub.api.'.$code)], $status);
    }
}
