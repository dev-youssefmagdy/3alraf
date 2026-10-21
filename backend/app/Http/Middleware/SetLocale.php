<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/** Storefront: locale from the {locale} URL prefix, validated against the tenant's locales. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = current_tenant();
        $locale = $request->route('locale');
        abort_if(! $tenant || ! in_array($locale, $tenant->locales, true), 404);

        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);
        view()->share('isRtl', (bool) Language::activeList()->firstWhere('code', $locale)?->isRtl());

        return $next($request);
    }
}
