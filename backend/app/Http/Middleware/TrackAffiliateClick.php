<?php

namespace App\Http\Middleware;

use App\Services\Affiliates\AffiliateTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackAffiliateClick
{
    public function __construct(private AffiliateTracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->has('aff')) {
            $this->tracker->track($request);
        }

        return $next($request);
    }
}
