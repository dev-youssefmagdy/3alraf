<?php

namespace App\Support\Marketing;

/** Canva asset links (MCP.md §6): https://canva.com/… or https://www.canva.com/… only — anything else is refused (null). */
final class CanvaUrl
{
    public const HOSTS = ['canva.com', 'www.canva.com'];

    public static function normalize(mixed $v): ?string
    {
        if (! is_string($v) || ($v = trim($v)) === '' || mb_strlen($v) > 500 || preg_match('/[\x00-\x20\x7F\\\\<>"\']/', $v)) {
            return null;
        }
        $p = parse_url($v);

        return ($p && strtolower($p['scheme'] ?? '') === 'https' && in_array(strtolower($p['host'] ?? ''), self::HOSTS, true) && ! isset($p['user']) && ! isset($p['port'])) ? $v : null;
    }
}
