<?php

namespace App\Http\Middleware;

use App\Services\Marketing\SeoRedirectService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Storefront: when a GET/HEAD page answers 404, look the path up in seo_redirects (slug changes made through SEO suggestions) and answer
 * 301 to the current path (query string kept). Normal pages never touch the table.
 */
class SeoRedirects
{
    public function __construct(private SeoRedirectService $redirects) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response->getStatusCode() !== 404 || ! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $response;
        }
        $first = (string) $request->segment(1);
        $locale = preg_match('/^[a-z]{2,3}(-[A-Za-z]{2})?$/', $first) ? $first : null;
        $to = $this->redirects->resolve($request->path(), $locale);
        if ($to === null) {
            return $response;
        }
        $qs = $request->getQueryString();

        return redirect()->to($to.($qs ? '?'.$qs : ''), 301);
    }
}
