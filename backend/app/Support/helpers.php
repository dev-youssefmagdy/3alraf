<?php

use App\Models\Tenant;

if (! function_exists('current_tenant')) {
    function current_tenant(): ?Tenant
    {
        return app()->bound('currentTenant') ? app('currentTenant') : null;
    }
}

if (! function_exists('central_domain')) {
    function central_domain(): string
    {
        return config('app.central_domain');
    }
}

if (! function_exists('tenant_url')) {
    function tenant_url(Tenant|string $tenant, string $path = '/'): string
    {
        $slug = $tenant instanceof Tenant ? $tenant->slug : $tenant;
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'http';
        $port = parse_url(config('app.url'), PHP_URL_PORT);

        return $scheme.'://'.$slug.'.'.central_domain().($port ? ':'.$port : '').'/'.ltrim($path, '/');
    }
}

if (! function_exists('selected_tenant_id')) {
    /** Tenant picked in the central admin switcher (null = all tenants). */
    function selected_tenant_id(): ?int
    {
        return current_tenant()?->id ?? (session('central_tenant_id') ?: null);
    }
}

if (! function_exists('tenant_slug_from_host')) {
    function tenant_slug_from_host(\Illuminate\Http\Request $request): ?string
    {
        $suffix = '.'.central_domain();
        $host = $request->getHost();

        return str_ends_with($host, $suffix) ? substr($host, 0, -strlen($suffix)) : null;
    }
}
