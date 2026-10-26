<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CentralContext;
use App\Http\Middleware\ResolveTenant;
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
            $central = config('app.central_domain');
            // Mobile JSON API: tenant from the subdomain host (names api.v1.*) or from the X-Tenant header on the central host (names api.v1.h.*).
            // Registered first so a tenant `/api/...` never reaches the storefront custom-page catch-all.
            Route::middleware(['api', 'api.context'])->domain('{tenant}.'.$central)->prefix('api/v1')->name('api.v1.')->group(base_path('routes/api_v1.php'));
            Route::middleware(['api', 'api.context'])->domain($central)->prefix('api/v1')->name('api.v1.h.')->group(base_path('routes/api_v1.php'));
            Route::middleware('api')->domain($central)->prefix('api/v1')->group(base_path('routes/api.php'));
            Route::middleware(['web', CentralContext::class, 'auth.active'])->domain($central)->group(base_path('routes/central.php'));
            Route::middleware(['web', 'tenant.resolve', 'auth.active'])->domain('{tenant}.'.$central)->group(function () {
                // order matters: /admin first, then the /{locale} storefront
                require base_path('routes/tenant_admin.php');
                require base_path('routes/storefront.php');
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        // Behind nginx/LB: trust only configured proxies (default: private ranges + loopback) and only our own hosts.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') === '*' ? '*' : array_filter(explode(',', (env('TRUSTED_PROXIES') ?: '127.0.0.1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'))));
        $middleware->trustHosts(at: fn () => ['^(.+\.)?'.preg_quote((string) config('app.central_domain'), '/').'$'], subdomains: false);
        $middleware->alias([
            'tenant.resolve' => ResolveTenant::class,
            'set.locale' => SetLocale::class,
            'admin.locale' => SetAdminLocale::class,
            'api.context' => \App\Http\Middleware\Api\ApiContext::class,
            'api.auth' => \App\Http\Middleware\Api\ApiAuth::class,
            'affiliate.track' => \App\Http\Middleware\TrackAffiliateClick::class,
            'ai.enabled' => \App\Http\Middleware\EnsureAiEnabled::class,
            'auth.active' => \App\Http\Middleware\EnsureAccountActive::class,
        ]);
        // Inactive-account logout must run before route auth (Authenticate is priority-sorted ahead of plain group middleware).
        $middleware->prependToPriorityList(before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class, prepend: \App\Http\Middleware\EnsureAccountActive::class);
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*')) {
                return null; // JSON 401 (ApiExceptionRenderer), never a redirect
            }

            if ($request->is('admin') || $request->is('admin/*')) {
                return $request->getHost() === central_domain() ? route('central.login') : route('tenant.login', ['tenant' => tenant_slug_from_host($request)]);
            }

            if ($request->is('*/affiliate/*') || $request->is('*/affiliate')) {
                return route('storefront.affiliate.login', ['tenant' => tenant_slug_from_host($request), 'locale' => $request->segment(1)]);
            }

            return route('storefront.login', ['tenant' => tenant_slug_from_host($request), 'locale' => $request->segment(1) ?: app()->getLocale()]);
        });
        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return $request->getHost() === central_domain() ? route('central.dashboard') : route('tenant.dashboard', ['tenant' => tenant_slug_from_host($request)]);
            }

            if ($request->is('*/affiliate/*') || $request->is('*/affiliate')) {
                return route('storefront.affiliate.dashboard', ['tenant' => tenant_slug_from_host($request), 'locale' => $request->segment(1)]);
            }

            return route('storefront.home', ['tenant' => tenant_slug_from_host($request), 'locale' => $request->segment(1) ?: app()->getLocale()]);
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
