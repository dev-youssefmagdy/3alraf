<?php

namespace App\Http\Middleware\Api;

use App\Http\Middleware\ResolveTenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\Affiliates\AffiliateTracker;
use App\Services\Growth\AttributionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-request context of the mobile API: tenant (subdomain host OR `X-Tenant` slug header), locale (Accept-Language limited to the
 * tenant's locales), guest cart token (`X-Cart-Token`) and affiliate attribution (`X-Affiliate-Ref` = affiliate uuid, same as the web cookie) and Growth first-touch (`X-Attribution`).
 */
class ApiContext
{
    public function __construct(private TenantRepositoryInterface $tenants, private ResolveTenant $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        Auth::forgetGuards(); // never carry a user over from a previous request (long-lived workers, tests)
        $slug = $request->route('tenant') ?? $request->header('X-Tenant') ?? $this->resolver->slugFromHost($request->getHost());
        $tenant = $slug ? $this->tenants->findBySlug(strtolower((string) $slug)) : null;
        abort_if(! $tenant, 404, __('store.api.tenant_not_found'));
        $this->resolver->activate($tenant, $request);

        $locale = $this->locale($request, $tenant->locales, $tenant->default_locale);
        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);

        session()->forget('cart_token');
        if ($token = $request->header('X-Cart-Token')) {
            session(['cart_token' => (string) $token]);
        }
        $request->attributes->set('api_context', true);
        if ($attr = AttributionService::fromHeader((string) $request->header('X-Attribution'))) {
            session(['growth_attr' => $attr['attr']]);
            ! empty($attr['gift_session']) && session(['gift_session_id' => $attr['gift_session']]);
            ! empty($attr['bundle_ab']) && session(['bundle_ab' => $attr['bundle_ab']]);
        }
        if ($ref = $request->header('X-Affiliate-Ref')) {
            $request->cookies->set(AffiliateTracker::COOKIE, (string) $ref);
        }

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);
        if ($cart = session('cart_token')) {
            $response->headers->set('X-Cart-Token', (string) $cart); // guest cart token to send back on the next calls
        }

        return $response;
    }

    private function locale(Request $request, array $allowed, string $default): string
    {
        foreach (preg_split('/\s*,\s*/', (string) $request->header('Accept-Language')) ?: [] as $part) {
            $code = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));
            if ($code !== '' && in_array($code, $allowed, true)) {
                return $code;
            }
        }

        return $default;
    }
}
