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

if (! function_exists('central_url')) {
    function central_url(string $path = '/'): string
    {
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'http';
        $port = parse_url(config('app.url'), PHP_URL_PORT);

        return $scheme.'://'.central_domain().($port ? ':'.$port : '').'/'.ltrim($path, '/');
    }
}

if (! function_exists('country_flag')) {
    /** ISO-3166 alpha-2 code -> flag emoji (regional indicator symbols). */
    function country_flag(?string $code): string
    {
        $code = strtoupper((string) $code);
        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            return '🌍';
        }

        return mb_chr(0x1F1E6 + ord($code[0]) - 65).mb_chr(0x1F1E6 + ord($code[1]) - 65);
    }
}

if (! function_exists('media_url')) {
    /** The ONLY way to build a public media URL: CDN base (MEDIA_URL) + stored path. */
    function media_url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return config('media.url').'/'.ltrim($path, '/');
    }
}

if (! function_exists('private_url')) {
    /** Signed, expiring URL for a file on the private disk (served only to authenticated tenant admins). */
    function private_url(?string $path, ?int $minutes = null): ?string
    {
        if (! $path) {
            return null;
        }

        return \Illuminate\Support\Facades\URL::temporarySignedRoute('tenant.files.private', now()->addMinutes($minutes ?? config('media.signed_ttl_minutes')), ['tenant' => current_tenant()?->slug, 'path' => $path]);
    }
}

if (! function_exists('api_route')) {
    /** URL of a mobile API route by its short name, in the same flavour (subdomain host / X-Tenant header) as the current request. */
    function api_route(string $name, array $params = []): string
    {
        $header = str_starts_with((string) request()->route()?->getName(), 'api.v1.h.');

        return route('api.v1.'.($header ? 'h.' : '').$name, $params);
    }
}

if (! function_exists('array_sum_bc')) {
    /** Exact integer sum of numeric strings (bcmath). */
    function array_sum_bc(array $values): string
    {
        return array_reduce($values, fn ($c, $v) => bcadd($c, (string) $v, 0), '0');
    }
}
