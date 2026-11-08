<?php

namespace App\Providers;

use App\Listeners\Erp\PostOperationsEvents;
use App\Listeners\Erp\PostOrderEvents;
use App\Listeners\NotifyCustomerOfOrder;
use App\Models\Setting;
use App\Services\Erp\Equity\ErpSettings;
use App\Services\Push\LogPushDispatcher;
use App\Services\Push\PushDispatcher;
use App\Services\Whatsapp\Chat\MediaScanner;
use App\Services\Whatsapp\Chat\NullMediaScanner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
        $this->app->bind(MediaScanner::class, NullMediaScanner::class);
        $this->app->bind(PushDispatcher::class, LogPushDispatcher::class);
        $this->app->scoped(ErpSettings::class);
        $this->app->scoped(\App\Services\Marketing\Meta\MetaAudit::class);   // per-request acting user of the Meta audit log (P6)
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Site timezone (setting `timezone`): set once per process (web, console, queue), never per request. Missing while installing → app default (UTC).
        if (($tz = Setting::get('timezone')) && in_array($tz, timezone_identifiers_list(), true)) {
            config(['app.timezone' => $tz]);
            date_default_timezone_set($tz);
        }
        // ERP strings live in resources/lang-erp (FileLoader merges every registered path per group)
        $this->app->make('translator')->getLoader()->addPath(resource_path('lang-erp'));
        $this->registerLangShims();
        // ERP-028: the ERP hub / sidebar item open for any ERP permission (reports-only accountant, view-only owners/assets...)
        Gate::define('erp.access', fn ($user) => method_exists($user, 'getAllPermissions')
            && $user->getAllPermissions()->contains(fn ($p) => str_starts_with($p->name, 'erp.') && $p->guard_name === 'admin'));
        // Marketer panel (/marketer, P4): open for any marketing permission (MarketingPermissions::PANEL); each page has its own `can:`.
        Gate::define('marketing.panel', fn ($user) => method_exists($user, 'getAllPermissions')
            && $user->getAllPermissions()->contains(fn ($p) => $p->guard_name === 'admin' && in_array($p->name, \App\Support\Marketing\MarketingPermissions::PANEL, true)));
        // Named limiters (docs/GAP_ANALYSIS §3); keyed per client IP (one shop, so no per-shop part).
        foreach (['cart' => 90, 'quote' => 60, 'checkout-validate' => 40, 'search' => 90, 'upload' => 10, 'returns' => 10, 'addresses' => 30, 'devices' => 30, 'payout' => 5, 'login-validate' => 30, 'webhooks' => 240] as $name => $perMinute) {
            RateLimiter::for($name, fn (Request $r) => Limit::perMinute($perMinute)->by($name.'|'.$r->ip()));
        }

        // Sensitive endpoints (login / register / password reset / e-mail codes / checkout / reviews): one bucket per route as well.
        foreach (['auth' => 10, 'auth-strict' => 5, 'checkout' => 20, 'reviews' => 10] as $name => $perMinute) {
            RateLimiter::for($name, fn (Request $r) => Limit::perMinute($perMinute)->by($name.'|'.$r->ip().'|'.self::routeKey($r)));
        }

        // Out-of-stock requests (public, unauthenticated form): short burst cap plus an hourly cap, per IP.
        RateLimiter::for('stock-requests', fn (Request $r) => [
            Limit::perMinute(3)->by('stock-requests-m|'.$r->ip()),
            Limit::perHour(15)->by('stock-requests-h|'.$r->ip()),
        ]);

        // Proof RE-upload (stricter than `upload`): per order token + IP; config/checkout.php.
        RateLimiter::for('proof-reupload', fn (Request $r) => Limit::perMinutes((int) config('checkout.reupload.window'), (int) config('checkout.reupload.attempts'))
            ->by('proof-reupload|'.$r->route('token').'|'.$r->ip()));

        // Mobile API default limiter: per authenticated user, else per IP. Never keyed by the raw bearer string
        // (garbage tokens would each get a fresh bucket); the user is only known once a guard has authenticated the request.
        RateLimiter::for('api', function (Request $r) {
            $user = $r->user();

            return Limit::perMinute(120)->by('api|'.($user ? $user::class.':'.$user->getAuthIdentifier() : 'ip:'.$r->ip()));
        });

        Event::subscribe(NotifyCustomerOfOrder::class);
        Event::subscribe(PostOrderEvents::class);
        Event::subscribe(PostOperationsEvents::class);
        RateLimiter::for('integration-ip', fn (Request $r) => Limit::perMinute(300)->by('integration-ip|'.$r->ip()));
        require base_path('routes/channels.php');

        // Super admins (guard `admin`) bypass every permission check.
        Gate::before(fn ($user) => method_exists($user, 'hasRole') && $user->hasRole('super-admin') ? true : null);
    }

    /**
     * lang/en is owned by another OS user (agents cannot create files there). Until lang/en/{group}.php exists, the English lines of these groups
     * are served from a key of an existing English file, so the code uses one key in every locale: gift_finder.* ← lang/en/store.php `gift_finder`.
     * Owner TODO: move that block to lang/en/gift_finder.php, then this shim does nothing (it only runs while the file is missing).
     */
    private function registerLangShims(): void
    {
        // lang/en is owned by another user: groups without an en file read their English strings from a host file (`store.gift_finder`, `admin.marketing`).
        foreach (['gift_finder' => 'store', 'marketing' => 'admin'] as $group => $host) {
            if (is_file(lang_path("en/{$group}.php"))) {
                continue;
            }
            $lines = (array) trans("{$host}.{$group}", [], 'en');
            if ($lines) {
                $this->app->make('translator')->addLines(collect(Arr::dot($lines))->mapWithKeys(fn ($v, $k) => ["{$group}.{$k}" => $v])->all(), 'en');
            }
        }
    }

    public static function routeKey(Request $r): string
    {
        $name = (string) $r->route()?->getName();

        return $name !== '' ? $name : $r->method().' '.$r->path();
    }
}
