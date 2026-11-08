<?php

namespace App\Support\Marketing;

/** WCAG 2.x relative luminance / contrast ratio of #rrggbb colours (theme suggestions). AA: 4.5 normal text, 3.0 large text / UI. */
final class Contrast
{
    public static function ratio(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return round((max($la, $lb) + 0.05) / (min($la, $lb) + 0.05), 2);
    }

    public static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $c = array_map(fn ($i) => hexdec(substr($hex, $i, 2)) / 255, [0, 2, 4]);
        $c = array_map(fn ($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    /** "#abc" / "#AABBCC" / "aabbcc" → "#aabbcc"; null when not a hex colour. */
    public static function normalizeHex(mixed $v): ?string
    {
        if (! is_string($v)) {
            return null;
        }
        $v = strtolower(trim($v));
        if (preg_match('/^#?([0-9a-f]{3})$/', $v, $m)) {
            return '#'.$m[1][0].$m[1][0].$m[1][1].$m[1][1].$m[1][2].$m[1][2];
        }

        return preg_match('/^#?([0-9a-f]{6})$/', $v, $m) ? '#'.$m[1] : null;
    }
}
