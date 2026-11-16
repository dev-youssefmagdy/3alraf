<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff panels: locale kept in session per panel (`admin_locale` | `marketer_locale`, switchable), default = site default locale (setting) or app locale.
 * Usage: `admin.locale` (admin) or `admin.locale:marketer`.
 */
class SetAdminLocale
{
    public function handle(Request $request, Closure $next, string $panel = 'admin'): Response
    {
        $codes = Language::activeList()->pluck('code')->all();
        $locale = $request->session()->get($panel.'_locale') ?: setting('default_locale') ?: config('app.locale');
        if ($codes && ! in_array($locale, $codes, true)) {
            $locale = $codes[0];
        }
        app()->setLocale($locale);
        view()->share('isRtl', (bool) Language::activeList()->firstWhere('code', $locale)?->isRtl());

        return $next($request);
    }
}
