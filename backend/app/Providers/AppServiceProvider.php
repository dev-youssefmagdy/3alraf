<?php

namespace App\Providers;

use App\Listeners\NotifyCustomerOfOrder;
use App\Models\Tenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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
        // Named limiters (docs/GAP_ANALYSIS §3); keyed per resolved tenant + client IP so one tenant cannot starve another
        // and a client cannot mint fresh buckets by varying the X-Tenant header (ignored on subdomain hosts).
        foreach (['cart' => 90, 'quote' => 60, 'checkout-validate' => 40, 'search' => 90, 'upload' => 10, 'returns' => 10, 'addresses' => 30, 'payout' => 5, 'login-validate' => 30, 'webhooks' => 240] as $name => $perMinute) {
            RateLimiter::for($name, fn (Request $r) => Limit::perMinute($perMinute)->by($name.'|'.self::tenantKey($r).'|'.$r->ip()));
        }

        // Sensitive endpoints (login / register / password reset / e-mail codes / checkout / reviews): one bucket per route as well.
        // The header-tenant API copy (api.v1.h.*) shares the bucket of its subdomain twin (api.v1.*).
        foreach (['auth' => 10, 'auth-strict' => 5, 'checkout' => 20, 'reviews' => 10] as $name => $perMinute) {
            RateLimiter::for($name, fn (Request $r) => Limit::perMinute($perMinute)->by($name.'|'.self::tenantKey($r).'|'.$r->ip().'|'.self::routeKey($r)));
        }

        // Proof RE-upload (stricter than `upload`): per tenant + order token + IP; config/checkout.php.
        RateLimiter::for('proof-reupload', fn (Request $r) => Limit::perMinutes((int) config('checkout.reupload.window'), (int) config('checkout.reupload.attempts'))
            ->by('proof-reupload|'.self::tenantKey($r).'|'.$r->route('token').'|'.$r->ip()));

        // Mobile API default limiter: per tenant + authenticated user, else per IP. Never keyed by the raw bearer string
        // (garbage tokens would each get a fresh bucket); the user is only known once a guard has authenticated the request.
        RateLimiter::for('api', function (Request $r) {
            $user = $r->user();

            return Limit::perMinute(120)->by('api|'.self::tenantKey($r).'|'.($user ? $user::class.':'.$user->getAuthIdentifier() : 'ip:'.$r->ip()));
        });

        Event::subscribe(NotifyCustomerOfOrder::class);
        require base_path('routes/channels.php');

        // Super admins bypass permission checks within their guard.
        Gate::before(fn ($user) => method_exists($user, 'hasRole') && $user->hasRole('super-admin') ? true : null);
    }

    /**
     * Tenant part of a limiter key. Limiters run before tenant resolution (ThrottleRequests is priority-sorted ahead of it), so the tenant is
     * resolved here: subdomain host → that subdomain's tenant (X-Tenant ignored); central-host API → the X-Tenant tenant if it exists; else "central".
     */
    public static function tenantKey(Request $r): string
    {
        if ($r->attributes->has('limiter_tenant')) {
            return $r->attributes->get('limiter_tenant');
        }
        $host = strtolower($r->getHost());
        $central = strtolower((string) config('app.central_domain'));
        $slug = match (true) {
            $central !== '' && str_ends_with($host, '.'.$central) => substr($host, 0, -strlen('.'.$central)),
            $r->is('api/*') && $r->hasHeader('X-Tenant') => strtolower(trim((string) $r->header('X-Tenant'))),
            default => null,
        };
        $id = $slug !== null && $slug !== '' ? Tenant::query()->where('slug', $slug)->value('id') : null;
        $key = $id ? 't'.$id : 'central';
        $r->attributes->set('limiter_tenant', $key);

        return $key;
    }

    public static function routeKey(Request $r): string
    {
        $name = (string) $r->route()?->getName();

        return $name !== '' ? preg_replace('/^api\.v1\.h\./', 'api.v1.', $name) : $r->method().' '.$r->path();
    }
}
