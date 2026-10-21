<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Central domain requests never have a tenant (guards against leaked state in long-lived processes). */
class CentralContext
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->forgetInstance('currentTenant');

        return $next($request);
    }
}
