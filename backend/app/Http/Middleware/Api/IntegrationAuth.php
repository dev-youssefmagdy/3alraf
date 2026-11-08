<?php

namespace App\Http\Middleware\Api;

use App\Models\IntegrationRequest;
use App\Services\Integration\IntegrationClientService;
use App\Services\Integration\SignatureService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route gate `integration.auth:{scope}[|{scope}...]` for the n8n API: bearer token ("{prefix}.{token}") + HMAC-SHA256(secret, "{X-Timestamp}.{raw body}"),
 * 5-minute replay window, scope check (any listed scope is enough), per-client throttle. Authentication failures are uniform (401) so tokens/clients
 * cannot be probed. Every authenticated call is logged in integration_requests (client, method, path, status; no body, no IP).
 */
class IntegrationAuth
{
    public function __construct(private IntegrationClientService $clients, private SignatureService $sig) {}

    public function handle(Request $request, Closure $next, string $scopes = ''): Response
    {
        Auth::forgetGuards();
        $bearer = (string) $request->bearerToken();
        $client = $bearer !== '' ? $this->clients->authenticate($bearer) : null;
        $ts = (string) $request->header(config('integration.hmac.timestamp_header'));
        $sigHeader = (string) $request->header(config('integration.hmac.header'));

        if (! $client || ! $this->sig->withinWindow($ts) || $sigHeader === '' || ! $this->sig->verify($client->hmac_secret, $ts, $request->getContent(), $sigHeader)) {
            return $this->deny(401, 'unauthenticated');
        }
        if ($scopes !== '' && ! $client->hasScope(...explode('|', $scopes))) {
            return $this->log($request, $client->id, $this->deny(403, 'forbidden_scope'));
        }
        $limit = (int) config('integration.throttle_per_minute');
        if (RateLimiter::tooManyAttempts($key = 'integration|'.$client->id, $limit)) {
            return $this->deny(429, 'too_many_requests')->header('Retry-After', (string) RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);

        $request->attributes->set('integration_client', $client);
        if (! $client->last_used_at || $client->last_used_at->lt(now()->subMinute())) {
            $client->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $this->log($request, $client->id, $next($request));
    }

    private function log(Request $request, int $clientId, Response $response): Response
    {
        try {
            IntegrationRequest::create([
                'integration_client_id' => $clientId, 'idempotency_key' => (string) Str::uuid(), 'request_hash' => hash('sha256', $request->method().'|'.$request->getRequestUri().'|'.$request->getContent()),
                'method' => $request->method(), 'path' => mb_substr($request->path(), 0, 160), 'status_code' => $response->getStatusCode(),
            ]);
        } catch (\Throwable $e) {
            report($e); // the log never breaks a call
        }

        return $response;
    }

    private function deny(int $status, string $code)
    {
        return response()->json(['success' => false, 'error' => $code, 'message' => __('admin.integration.api.'.$code)], $status);
    }
}
