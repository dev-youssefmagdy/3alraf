<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Named limiters (docs/GAP_ANALYSIS §3); keyed per client + host so one tenant cannot starve another.
        foreach (['cart' => 90, 'quote' => 60, 'checkout-validate' => 40, 'search' => 90, 'upload' => 10, 'returns' => 10, 'addresses' => 30, 'payout' => 5, 'login-validate' => 30, 'webhooks' => 240] as $name => $perMinute) {
            RateLimiter::for($name, fn (Request $r) => Limit::perMinute($perMinute)->by($name.'|'.$r->getHost().'|'.$r->header('X-Tenant').'|'.$r->ip()));
        }

        // Mobile API default limiter: per tenant + bearer token (or IP)
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by('api|'.$r->getHost().'|'.$r->header('X-Tenant').'|'.($r->bearerToken() ?: $r->ip())));

        // Super admins bypass permission checks within their guard.
        Gate::before(fn ($user) => method_exists($user, 'hasRole') && $user->hasRole('super-admin') ? true : null);
    }
}
