<?php

namespace App\Support;

/** Tenant-country phone handling: normalise typed input to the national number, build E.164 / display forms. */
final class Phone
{
    public static function config(?string $iso): ?array
    {
        return $iso ? config('phone.countries.'.strtoupper($iso)) : null;
    }

    /** Dial code (e.g. +20) of the current tenant, null when unknown. */
    public static function tenantCode(): ?string
    {
        return self::config(current_tenant()?->country)['dial'] ?? null;
    }

    public static function ascii(string $v): string
    {
        return strtr($v, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
    }

    /**
     * @return array{0:?string,1:?string} [national number or null, error key: invalid|wrong_country|null]
     */
    public static function normalize(string $raw, ?string $iso): array
    {
        $raw = trim(self::ascii($raw));
        if ($raw === '') {
            return [null, null];
        }
        if (preg_match('/[^\d\s\-().+]/', $raw)) {
            return [null, 'invalid'];
        }
        $cfg = self::config($iso);
        $explicit = str_starts_with($raw, '+') || str_starts_with(preg_replace('/\D/', '', $raw), '00');
        $d = preg_replace('/\D/', '', $raw);
        if ($d === '') {
            return [null, 'invalid'];
        }
        if (! $cfg) {
            return [self::lenOk(ltrim($d, '+')) ? $d : null, self::lenOk($d) ? null : 'invalid'];
        }
        $code = ltrim($cfg['dial'], '+');
        $trunk = $cfg['trunk'];
        if ($explicit) {
            $d = str_starts_with($d, '00') ? substr($d, 2) : $d;
            if (! str_starts_with($d, $code)) {
                return [null, 'wrong_country'];
            }
            $d = substr($d, strlen($code));
        } elseif ($trunk === '' || ! str_starts_with($d, $trunk)) {
            // bare "201008489183": accept the own code only when what remains is a valid number
            if (str_starts_with($d, $code) && self::valid(self::withTrunk(substr($d, strlen($code)), $trunk), $cfg) && ! self::valid(self::withTrunk($d, $trunk), $cfg)) {
                $d = substr($d, strlen($code));
            }
        }
        $d = self::withTrunk($d, $trunk);

        return self::valid($d, $cfg) ? [$d, null] : [null, 'invalid'];
    }

    private static function withTrunk(string $d, string $trunk): string
    {
        return ($trunk !== '' && ! str_starts_with($d, $trunk)) ? $trunk.$d : $d;
    }

    private static function lenOk(string $d): bool
    {
        return strlen($d) >= config('phone.min') && strlen($d) <= config('phone.max');
    }

    private static function valid(string $d, array $cfg): bool
    {
        return $cfg['pattern'] ? (bool) preg_match($cfg['pattern'], $d) : self::lenOk($d);
    }

    /** E.164 (+201008489183): code + national number without the trunk 0. */
    public static function e164(?string $code, ?string $national): ?string
    {
        $national = preg_replace('/\D/', '', (string) $national);
        if ($national === '') {
            return null;
        }
        $iso = collect(config('phone.countries'))->search(fn ($c) => $c['dial'] === $code);
        $trunk = $iso ? config('phone.countries.'.$iso.'.trunk') : '';
        if ($trunk !== '' && str_starts_with($national, $trunk)) {
            $national = substr($national, strlen($trunk));
        }

        return ($code ?? '').$national;
    }

    /** Human form: +20 100 848 9183. */
    public static function display(?string $code, ?string $national): ?string
    {
        $e = self::e164($code, $national);
        if ($e === null) {
            return null;
        }
        if (! $code) {
            return $e;
        }
        $rest = substr($e, strlen($code));

        return $code.' '.implode(' ', array_filter([substr($rest, 0, 3), substr($rest, 3, 3), substr($rest, 6)], fn ($p) => $p !== ''));
    }
}
