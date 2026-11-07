<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /** Site-wide setting (table `settings`, cached per request): exact key, or a dot path into an array value ("growth.enabled"). */
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
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
    /** Signed, expiring URL for a file on the private disk (served only to authenticated admins). */
    function private_url(?string $path, ?int $minutes = null): ?string
    {
        if (! $path) {
            return null;
        }

        return \Illuminate\Support\Facades\URL::temporarySignedRoute('admin.files.private', now()->addMinutes($minutes ?? config('media.signed_ttl_minutes')), ['path' => $path]);
    }
}

if (! function_exists('api_route')) {
    /** URL of a mobile API route by its short name (`api.v1.{name}`). */
    function api_route(string $name, array $params = []): string
    {
        return route('api.v1.'.$name, $params);
    }
}

if (! function_exists('array_sum_bc')) {
    /** Exact integer sum of numeric strings (bcmath). */
    function array_sum_bc(array $values): string
    {
        return array_reduce($values, fn ($c, $v) => bcadd($c, (string) $v, 0), '0');
    }
}

if (! function_exists('storefront_url')) {
    /** Absolute storefront URL in the default locale: storefront_url('product/x') → APP_URL/ar/product/x. */
    function storefront_url(string $path = '/'): string
    {
        $path = trim($path, '/');

        return url('/'.(setting('default_locale') ?: config('app.locale')).($path !== '' ? '/'.$path : ''));
    }
}

if (! function_exists('locale_is_rtl')) {
    /** Whether a language (default: the current locale) is written right-to-left (active languages table). */
    function locale_is_rtl(?string $locale = null): bool
    {
        return (bool) \App\Models\Language::activeList()->firstWhere('code', $locale ?? app()->getLocale())?->isRtl();
    }
}
