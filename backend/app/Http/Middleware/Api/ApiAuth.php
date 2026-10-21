<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token auth for the two token types. `api.auth:customer|affiliate[,optional]`.
 * The token must carry the matching ability (a customer token can never open affiliate endpoints and vice versa) and its owner must belong
 * to the current tenant (the tenant global scope hides other tenants' users, so their tokens resolve to nobody).
 * The resolved user is also set on the legacy session guard (`web` / `affiliate`) so the existing Services/FormRequests work unchanged.
 */
class ApiAuth
{
    public function handle(Request $request, Closure $next, string $type, ?string $mode = null): Response
    {
        $api = $type === 'affiliate' ? 'affiliate_api' : 'customer_api';
        $legacy = $type === 'affiliate' ? 'affiliate' : 'web';

        if ($mode === 'optional' && ! $request->bearerToken()) {
            return $next($request);
        }
        $user = Auth::guard($api)->user();
        if (! $user || ! $user->tokenCan($type) || ($user->is_active ?? true) === false) {
            throw new AuthenticationException('Unauthenticated.', [$api]);
        }
        if ($type === 'affiliate' && ! $user->isApproved()) {
            throw new AuthenticationException('Unauthenticated.', [$api]);
        }
        Auth::guard($legacy)->setUser($user);
        Auth::shouldUse($legacy);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
