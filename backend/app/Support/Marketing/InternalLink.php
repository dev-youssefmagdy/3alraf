<?php

namespace App\Support\Marketing;

/**
 * Links a suggestion may carry (banner link, blog internal links, CTA): ONLY this store. Accepts a site-relative path ("/ar/product/x") or an
 * absolute http(s) URL on the store host (APP_URL host / config app.host); returns the normalised site-relative path, null when not allowed.
 * Never: other hosts, protocol-relative "//x", javascript:/data:, control characters, back-office paths (/admin, /marketer, /api).
 */
final class InternalLink
{
    private const BLOCKED_PREFIXES = ['/admin', '/marketer', '/api', '/webhooks', '/internal', '/storage/app'];

    public static function normalize(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || strlen($url) > 500 || preg_match('/[\x00-\x20\x7F\\\\<>"\']/', $url)) {
            return null;
        }
        if ($url[0] === '/') {
            if (str_starts_with($url, '//')) {
                return null;
            }
            $path = $url;
        } else {
            $parts = parse_url($url);
            if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
                return null;
            }
            if (! in_array(strtolower((string) ($parts['host'] ?? '')), self::hosts(), true)) {
                return null;
            }
            $path = ($parts['path'] ?? '/') ?: '/';
            $path .= isset($parts['query']) ? '?'.$parts['query'] : '';
            $path .= isset($parts['fragment']) ? '#'.$parts['fragment'] : '';
        }
        $lower = strtolower(rawurldecode(parse_url($path, PHP_URL_PATH) ?: '/'));
        if (str_contains($lower, '..')) {
            return null;
        }
        foreach (self::BLOCKED_PREFIXES as $p) {
            if ($lower === $p || str_starts_with($lower, $p.'/')) {
                return null;
            }
        }

        return $path;
    }

    public static function isInternal(?string $url): bool
    {
        return self::normalize($url) !== null;
    }

    /** @return list<string> */
    public static function hosts(): array
    {
        return array_values(array_unique(array_filter(array_map('strtolower', [
            (string) parse_url((string) config('app.url'), PHP_URL_HOST), (string) config('app.host'),
        ]))));
    }
}
