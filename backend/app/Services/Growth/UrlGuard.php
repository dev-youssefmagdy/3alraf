<?php

namespace App\Services\Growth;

/** Webhook targets: http(s) only (https required in production), no credentials, no loopback/private/link-local literals or internal names. */
class UrlGuard
{
    public static function isSafe(string $url): bool
    {
        $p = parse_url($url);
        if (! $p || empty($p['host']) || isset($p['user']) || isset($p['pass'])) {
            return false;
        }
        $scheme = strtolower($p['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true) || ($scheme === 'http' && config('growth.events.require_https'))) {
            return false;
        }
        $host = strtolower(trim($p['host'], '[]'));
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal') || str_ends_with($host, '.localhost')) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        // Resolved names must not point at private space (skip when DNS gives nothing: the request will fail on its own).
        foreach (@gethostbynamel($host) ?: [] as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
