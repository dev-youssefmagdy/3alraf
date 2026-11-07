<?php

namespace App\Http\Middleware;

use App\Services\Growth\GrowthAccess;
use Closure;
use Illuminate\Http\Request;

/** Route gate `growth.enabled`: the `growth` master switch (setting `growth.enabled`). */
class EnsureGrowthEnabled
{
    public function __construct(private GrowthAccess $access) {}

    public function handle(Request $request, Closure $next)
    {
        if ($this->access->enabled()) {
            return $next($request);
        }
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => __('growth_hub.disabled')], 403);
        }

        return response()->view('admin.growth.disabled', [], 403);
    }
}
