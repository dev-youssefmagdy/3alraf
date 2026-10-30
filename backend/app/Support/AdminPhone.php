<?php

namespace App\Support;

/** Admin phone numbers are stored as E.164 digits without "+" (e.g. 201001234567); built from a country + typed number. */
final class AdminPhone
{
    /** @return string|null digits, or null when the number is not valid for that country */
    public static function digits(string $iso, string $raw): ?string
    {
        $cfg = Phone::config($iso);
        if (! $cfg) {
            return null;
        }
        [$national, $err] = Phone::normalize($raw, strtoupper($iso));
        if ($national === null || $err) {
            return null;
        }
        $e164 = Phone::e164($cfg['dial'], $national);

        return $e164 ? preg_replace('/\D/', '', $e164) : null;
    }

    /** ISO => dial code for the login selector. */
    public static function countries(): array
    {
        return collect(config('phone.countries'))->map(fn ($c) => $c['dial'])->all();
    }

    public static function mask(string $digits): string
    {
        return '+'.substr($digits, 0, 3).str_repeat('•', max(strlen($digits) - 5, 2)).substr($digits, -2);
    }
}
