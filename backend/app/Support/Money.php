<?php

namespace App\Support;

/** All money math goes through here (decimal(14,2) strings, bcmath, HALF_UP). See docs/CALCULATIONS.md. */
final class Money
{
    public const SCALE = 2;

    public static function round(string|int|float $v, int $scale = self::SCALE): string
    {
        $v = self::str($v);
        $adj = '0.'.str_repeat('0', $scale).'5';
        $r = bccomp($v, '0', 12) >= 0 ? bcadd($v, $adj, $scale + 1) : bcsub($v, $adj, $scale + 1);

        return bcadd($r, '0', $scale); // truncates -> half away from zero
    }

    public static function add(string|int|float ...$v): string
    {
        return self::round(array_reduce($v, fn ($c, $x) => bcadd($c, self::str($x), 8), '0'));
    }

    public static function sub(string|int|float $a, string|int|float $b): string
    {
        return self::round(bcsub(self::str($a), self::str($b), 8));
    }

    public static function mul(string|int|float $a, string|int|float $b): string
    {
        return self::round(bcmul(self::str($a), self::str($b), 8));
    }

    public static function div(string|int|float $a, string|int|float $b): string
    {
        return self::round(bcdiv(self::str($a), self::str($b), 8));
    }

    /** $pct percent of $amount */
    public static function percent(string|int|float $amount, string|int|float $pct): string
    {
        return self::round(bcdiv(bcmul(self::str($amount), self::str($pct), 8), '100', 8));
    }

    public static function min(string|int|float $a, string|int|float $b): string
    {
        return bccomp(self::str($a), self::str($b), 8) <= 0 ? self::round($a) : self::round($b);
    }

    public static function max(string|int|float $a, string|int|float $b): string
    {
        return bccomp(self::str($a), self::str($b), 8) >= 0 ? self::round($a) : self::round($b);
    }

    public static function cmp(string|int|float $a, string|int|float $b): int
    {
        return bccomp(self::round($a), self::round($b), self::SCALE);
    }

    public static function format(string|int|float $v, ?string $symbol = null): string
    {
        $symbol ??= current_tenant()?->currency_symbol ?? '';

        return trim(number_format((float) self::round($v), 2).' '.$symbol);
    }

    private static function str(string|int|float $v): string
    {
        return is_float($v) ? number_format($v, 8, '.', '') : (string) ($v === '' ? '0' : $v);
    }
}
