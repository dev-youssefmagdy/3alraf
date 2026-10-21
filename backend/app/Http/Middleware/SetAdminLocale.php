<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Admin panels: locale kept in session (switchable), default = tenant default or app locale. */
class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $codes = Language::activeList()->pluck('code')->all();
        $locale = $request->session()->get('admin_locale') ?: current_tenant()?->default_locale ?: config('app.locale');
        if ($codes && ! in_array($locale, $codes, true)) {
            $locale = $codes[0];
        }
        app()->setLocale($locale);
        view()->share('isRtl', (bool) Language::activeList()->firstWhere('code', $locale)?->isRtl());

        return $next($request);
    }
}
