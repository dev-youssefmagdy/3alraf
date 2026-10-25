<?php

namespace App\Support;

/** Links admins may store for public rendering: absolute http(s) URLs or site-relative paths ("/x", never "//host" or "/\host"). */
final class SafeUrl
{
    public static function isSafe(?string $url): bool
    {
        $url = (string) $url;
        if ($url === '' || preg_match('/[\x00-\x20\x7F\\\\]/', $url)) {
            return false;
        }
        if ($url[0] === '/') {
            return ! str_starts_with($url, '//');
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false && parse_url($url, PHP_URL_HOST);
    }

    /** The URL when safe, else null (render-time filter for legacy rows). */
    public static function orNull(?string $url): ?string
    {
        return self::isSafe($url) ? $url : null;
    }
}
