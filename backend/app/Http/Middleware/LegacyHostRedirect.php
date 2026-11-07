<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * The only subdomain-aware code (single-store ruling §1.12): requests to a legacy shop host `{any}.APP_HOST` (old per-country stores,
 * printed QR codes, affiliate links, sent e-mails, old app builds) are answered with a permanent 301 to the same path + query on APP_URL.
 * Gateway callbacks (`webhooks/*`, POST — clients do not follow a 301) and the JSON APIs (`api/*`) are served directly on the legacy host;
 * every URL generated while serving them (payment return/webhook URLs, image/share links in API responses) uses APP_URL, never the legacy host.
 */
class LegacyHostRedirect
{
    /** Paths answered on a legacy host without redirecting. */
    public const SERVE_DIRECTLY = ['webhooks/*', 'api/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $host = (string) config('app.host');
        $current = strtolower($request->getHost());
        if ($host === '' || $current === $host || ! str_ends_with($current, '.'.$host)) {
            return $next($request);
        }
        if ($request->is(...self::SERVE_DIRECTLY)) {
            URL::forceRootUrl(rtrim((string) config('app.url'), '/'));

            return $next($request);
        }

        $base = rtrim((string) config('app.url'), '/');
        $query = $request->getQueryString();

        return redirect()->away($base.$request->getPathInfo().($query !== null && $query !== '' ? '?'.$query : ''), 301);
    }
}
