<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/** Storefront: locale from the {locale} URL prefix, validated against the site locales (setting `locales`). */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');
        abort_if(! in_array($locale, (array) setting('locales', [config('app.locale')]), true), 404);

        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);
        view()->share('isRtl', (bool) Language::activeList()->firstWhere('code', $locale)?->isRtl());

        return $next($request);
    }
}
