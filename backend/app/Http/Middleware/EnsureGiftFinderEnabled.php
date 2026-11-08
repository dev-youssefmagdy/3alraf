<?php

namespace App\Http\Middleware;

use App\Services\GiftFinder\GiftFinderSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route gate `gift_finder.enabled` for the storefront / mobile API Gift Finder while the switch (Settings → General) is off:
 * JSON callers get 403 `feature_disabled` (same contract the app already handles: hide the section), pages get 404.
 */
class EnsureGiftFinderEnabled
{
    public function __construct(private GiftFinderSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->settings->enabled()) {
            return $next($request);
        }
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => false, 'error' => 'feature_disabled', 'message' => __('gift_finder.unavailable')], 403);
        }
        abort(404);
    }
}
