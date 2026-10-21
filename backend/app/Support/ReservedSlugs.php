<?php

namespace App\Support;

/** Top-level paths a custom page (`tenant-domain/{slug}`) must not take: config('tenancy.reserved_slugs') + active locale codes + `product`, `page`, `affiliate`... */
class ReservedSlugs
{
    public static function all(): array
    {
        return array_values(array_unique(array_merge(
            config('tenancy.reserved_slugs', []),
            \App\Models\Language::query()->pluck('code')->all(),
        )));
    }

    public static function isReserved(string $slug): bool
    {
        // 2-3 letter slugs would be swallowed by the /{locale} route group
        return in_array(strtolower($slug), self::all(), true) || preg_match('/^[a-z]{2,3}$/i', $slug) === 1;
    }
}
