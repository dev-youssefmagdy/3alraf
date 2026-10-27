<?php

namespace App\Http\Middleware;

use App\Models\StorefrontVisit;
use App\Services\Growth\AttributionService;
use App\Services\Growth\GrowthFeatures;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Growth traffic: first-touch attribution capture + daily visit counters on storefront catalog pages and the API product page.
 * Stores no IP / user agent / session id (unique sessions dedupe via a 26h cache key built from a hash). Never breaks the request.
 */
class TrackStorefrontTraffic
{
    public function __construct(private GrowthFeatures $features) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        try {
            $this->track($request, $response);
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }

    private function track(Request $request, Response $response): void
    {
        $tenant = current_tenant();
        if (! $request->isMethod('GET') || ! $tenant || AttributionService::isBot($request->userAgent())
            || (! $this->features->active('integration_api', $tenant) && ! $this->features->active('brief', $tenant))) {
            return;
        }
        $name = (string) $request->route()?->getName();
        if (! in_array($name, ['storefront.home', 'storefront.product.show', 'storefront.category.show', 'storefront.brand.show', 'storefront.tag.show', 'storefront.search', 'products.show', 'api.v1.products.show'], true) && ! str_ends_with($name, 'products.show')) {
            return;
        }
        AttributionService::capture($request);
        if ($response->getStatusCode() !== 200) {
            return;
        }
        $channel = AttributionService::currentChannel($request);
        $date = now($tenant->timezone ?: config('app.timezone'))->toDateString();
        $sid = $request->hasSession() ? $request->session()->getId() : null;
        $this->count($tenant->id, $date, 0, $channel, $sid);
        if ($pid = (int) $request->attributes->get('growth_product_id')) {
            $this->count($tenant->id, $date, $pid, $channel, $sid);
        }
    }

    private function count(int $tenantId, string $date, int $productId, string $channel, ?string $sid): void
    {
        $key = ['tenant_id' => $tenantId, 'visit_date' => $date, 'product_id' => $productId, 'channel' => $channel];
        $first = $sid && Cache::add('growth:sess:'.sha1($tenantId.'|'.$date.'|'.$productId.'|'.$channel.'|'.$sid), 1, now()->addHours(26));
        DB::table('storefront_visits')->insertOrIgnore($key + ['visits' => 0, 'unique_sessions' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('storefront_visits')->where($key)->update(['visits' => DB::raw('visits + 1'), 'unique_sessions' => DB::raw('unique_sessions + '.($first ? 1 : 0)), 'updated_at' => now()]);
    }
}
