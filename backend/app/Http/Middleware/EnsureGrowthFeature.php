<?php

namespace App\Http\Middleware;

use App\Services\Growth\GrowthFeatures;
use Closure;
use Illuminate\Http\Request;

/** Route gate `growth.feature:{key}`: web OFF = 404 page, JSON/AJAX = 403 feature_disabled. */
class EnsureGrowthFeature
{
    public function __construct(private GrowthFeatures $features) {}

    public function handle(Request $request, Closure $next, string $key)
    {
        if ($this->features->active($key, current_tenant())) {
            return $next($request);
        }
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'error' => 'feature_disabled', 'message' => __('growth_hub.feature_disabled')], 403);
        }
        abort(404);
    }
}
