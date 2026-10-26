<?php

namespace App\Http\Middleware;

use App\Services\Ai\AiAccess;
use Closure;
use Illuminate\Http\Request;

/** Route gate `ai.enabled:{feature}`: platform switch for the tenant + per-feature toggle. No feature (or `any`) = master switch only. */
class EnsureAiEnabled
{
    public function __construct(private AiAccess $access) {}

    public function handle(Request $request, Closure $next, string $feature = 'any')
    {
        $tenant = current_tenant();
        $ok = $tenant && ($feature === 'any' ? $this->access->enabled($tenant) : $this->access->feature($feature, $tenant));
        if ($ok) {
            return $next($request);
        }
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => __('ai.errors.disabled')], 403);
        }

        return response()->view('tenant.ai.disabled', ['feature' => $feature], 403);
    }
}
