<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\LegacyHostRedirect;
use App\Http\Middleware\SetAdminLocale;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // One host (APP_URL). Legacy `{any}.host` requests are 301-redirected by LegacyHostRedirect (global middleware), except webhooks/* and api/*.
            // Mobile JSON API, registered first so `/api/...` never reaches the storefront custom-page catch-all.
            Route::middleware(['api', 'api.context'])->prefix('api/v1')->name('api.v1.')->group(base_path('routes/api_v1.php'));
            Route::middleware('api')->prefix('api/v1')->group(base_path('routes/api.php'));
            // Node WhatsApp gateway -> Laravel session events (HMAC-signed).
            Route::middleware('api')->post('internal/whatsapp/events', \App\Http\Controllers\Webhook\WhatsappEventController::class)->name('internal.whatsapp.events');
            // n8n integration API (bearer + HMAC; read-only: ping, health, gift-finder reads).
            Route::middleware(['api', 'integration.ip', 'throttle:integration-ip'])->prefix('api/integration/v1')->name('integration.')->group(base_path('routes/api_integration.php'));
            // Marketing internal API for the MCP server (service token, no session; read-only in P4).
            Route::middleware('api')->prefix('api/internal/marketing')->name('marketing-api.')->group(base_path('routes/api_marketing.php'));
            // order matters: /admin and /marketer first, then the /{locale} storefront (its `/{slug}` custom-page catch-all is last)
            Route::middleware(['web', 'auth.active'])->group(base_path('routes/admin.php'));
            Route::middleware(['web', 'auth.active'])->group(base_path('routes/marketer.php'));
            // seo.redirects: 301 for old slugs (P5 SEO suggestions), only when a storefront page answers 404
            Route::middleware(['web', 'auth.active', 'seo.redirects'])->group(base_path('routes/storefront.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        // Behind nginx/LB: trust only configured proxies (default: private ranges + loopback) and only our own hosts.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') === '*' ? '*' : array_filter(explode(',', (env('TRUSTED_PROXIES') ?: '127.0.0.1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'))));
        // the site host + its subdomains (legacy shop hosts are only redirected / served for webhooks + API)
        $middleware->trustHosts(at: fn () => ['^(.+\.)?'.preg_quote((string) config('app.host'), '/').'$'], subdomains: false);
        $middleware->prepend(LegacyHostRedirect::class);
        $middleware->alias([
            'set.locale' => SetLocale::class,
            'admin.locale' => SetAdminLocale::class,
            'api.context' => \App\Http\Middleware\Api\ApiContext::class,
            'api.auth' => \App\Http\Middleware\Api\ApiAuth::class,
            'affiliate.track' => \App\Http\Middleware\TrackAffiliateClick::class,
            'integration.ip' => \App\Http\Middleware\Api\IntegrationIpAllowlist::class,
            'integration.auth' => \App\Http\Middleware\Api\IntegrationAuth::class,
            'attribution.capture' => \App\Http\Middleware\CaptureAttribution::class,
            'gift_finder.enabled' => \App\Http\Middleware\EnsureGiftFinderEnabled::class,
            'auth.active' => \App\Http\Middleware\EnsureAccountActive::class,
            'marketing.token' => \App\Http\Middleware\Api\MarketingTokenAuth::class,
            'seo.redirects' => \App\Http\Middleware\SeoRedirects::class,
        ]);
        // Inactive-account logout must run before route auth (Authenticate is priority-sorted ahead of plain group middleware).
        $middleware->prependToPriorityList(before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class, prepend: \App\Http\Middleware\EnsureAccountActive::class);
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*')) {
                return null; // JSON 401 (ApiExceptionRenderer), never a redirect
            }
            if ($request->is('admin') || $request->is('admin/*') || $request->is('marketer') || $request->is('marketer/*')) {
                return route('admin.login');
            }
            $locale = $request->segment(1) ?: (string) setting('default_locale', app()->getLocale());
            if ($request->is('*/affiliate/*') || $request->is('*/affiliate')) {
                return route('storefront.affiliate.login', ['locale' => $locale]);
            }

            return route('storefront.login', ['locale' => $locale]);
        });
        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.welcome');
            }
            $locale = $request->segment(1) ?: (string) setting('default_locale', app()->getLocale());
            if ($request->is('*/affiliate/*') || $request->is('*/affiliate')) {
                return route('storefront.affiliate.dashboard', ['locale' => $locale]);
            }

            return route('storefront.home', ['locale' => $locale]);
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(new \App\Exceptions\ApiExceptionRenderer);
        // Error tracker: reports to Sentry when SENTRY_LARAVEL_DSN is set (no-op otherwise).
        \Sentry\Laravel\Integration::handles($exceptions);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
