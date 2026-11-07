<?php

namespace App\Providers;

use App\Listeners\EmitGrowthEvents;
use App\Listeners\NotifyCustomerOfOrder;
use App\Models\Setting;
use App\Services\Ai\AiAccess;
use App\Services\Growth\ApprovalService;
use App\Services\Growth\BundleProposalService;
use App\Services\Growth\ExperimentLabService;
use App\Services\Growth\GrowthFeatures;
use App\Services\Growth\InventoryDecisionService;
use App\Services\Growth\SmallBatchService;
use App\Services\Whatsapp\Chat\MediaScanner;
use App\Services\Whatsapp\Chat\NullMediaScanner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
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
        $this->app->bind(\App\Services\Push\PushDispatcher::class, \App\Services\Push\LogPushDispatcher::class);
        $this->app->scoped(\App\Services\Erp\Equity\ErpSettings::class);
        $this->app->scoped(AiAccess::class);
        $this->app->singleton(ApprovalService::class); // module handlers register on it
        $this->app->resolving(ApprovalService::class, fn ($a) => $a->register('experiment_budget', fn ($ap) => app(ExperimentLabService::class)->materializeBudget($ap)));
        $this->app->resolving(ApprovalService::class, fn ($a) => $a->register('batch_reorder', fn ($ap) => app(SmallBatchService::class)->materializeDecision($ap)));
        $this->app->resolving(ApprovalService::class, fn ($a) => $a->register('inventory_reorder', fn ($ap) => app(InventoryDecisionService::class)->materialize($ap)));
        $this->app->resolving(ApprovalService::class, fn ($a) => $a->register('bundle_draft', fn ($ap) => app(BundleProposalService::class)->materialize($ap)));
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
        // ERP-028: the ERP hub / sidebar item open for any ERP permission (reports-only accountant, view-only owners/assets...)
        Gate::define('erp.access', fn ($user) => method_exists($user, 'getAllPermissions')
            && $user->getAllPermissions()->contains(fn ($p) => str_starts_with($p->name, 'erp.') && $p->guard_name === 'admin'));
        Blade::if('growthFeature', fn (string $key) => app(GrowthFeatures::class)->active($key));
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
        Event::subscribe(EmitGrowthEvents::class);
        Event::subscribe(\App\Listeners\Erp\PostOrderEvents::class);
        Event::subscribe(\App\Listeners\Erp\PostOperationsEvents::class);
        EmitGrowthEvents::hookModels();
        RateLimiter::for('integration-ip', fn (Request $r) => Limit::perMinute(300)->by('integration-ip|'.$r->ip()));
        require base_path('routes/channels.php');

        // Super admins (guard `admin`) bypass every permission check.
        Gate::before(fn ($user) => method_exists($user, 'hasRole') && $user->hasRole('super-admin') ? true : null);
    }

    public static function routeKey(Request $r): string
    {
        $name = (string) $r->route()?->getName();

        return $name !== '' ? $name : $r->method().' '.$r->path();
    }
}
