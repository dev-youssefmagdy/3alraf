<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per request: a deactivated admin/customer or a non-approved affiliate still holding a session (or remember cookie) on any of the
 * session guards is logged out of that guard before route auth runs (priority-listed ahead of Authenticate in bootstrap/app.php).
 */
class EnsureAccountActive
{
    public const GUARDS = ['web', 'affiliate', 'tenant', 'central'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()) {
            foreach (self::GUARDS as $guard) {
                $auth = auth()->guard($guard);
                $user = $auth->user();
                if ($user && method_exists($user, 'isLockedOut') && $user->isLockedOut()) {
                    $auth->logout();
                }
            }
        }

        return $next($request);
    }
}
