<?php

namespace App\Http\Middleware;

use App\Repositories\Contracts\TenantRepositoryInterface;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/** Resolves the tenant from the `{tenant}.central_domain` subdomain; 404 if unknown/inactive. */
class ResolveTenant
{
    public function __construct(private TenantRepositoryInterface $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant') ?? $this->slugFromHost($request->getHost());
        $tenant = $slug ? $this->tenants->findBySlug((string) $slug) : null;
        if (! $tenant) {
            // unknown or inactive store: friendly Arabic page linking back to the central landing
            throw new HttpResponseException(response()->view('errors.store-unavailable', [], 404));
        }

        app()->instance('currentTenant', $tenant);
        config(['app.timezone' => $tenant->timezone]);
        date_default_timezone_set($tenant->timezone);
        URL::defaults(['tenant' => $tenant->slug]);
        $request->route()?->forgetParameter('tenant');

        return $next($request);
    }

    private function slugFromHost(string $host): ?string
    {
        $suffix = '.'.central_domain();

        return str_ends_with($host, $suffix) ? substr($host, 0, -strlen($suffix)) : null;
    }
}
