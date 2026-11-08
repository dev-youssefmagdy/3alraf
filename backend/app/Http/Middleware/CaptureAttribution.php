<?php

namespace App\Http\Middleware;

use App\Services\Storefront\AttributionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route gate `attribution.capture`: first-touch attribution (utm / ref / click-id presence / external referrer) on storefront GET pages, kept in the
 * session and copied to carts.attribution / orders.attribution + orders.channel (docs/CALCULATIONS.md "Order attribution"). Stores no IP / user agent.
 * Bots are ignored. Never breaks the request.
 */
class CaptureAttribution
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if ($request->isMethod('GET') && ! AttributionService::isBot($request->userAgent())) {
                AttributionService::capture($request);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $next($request);
    }
}
