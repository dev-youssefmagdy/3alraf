<?php

namespace App\Support;

use App\Rules\DecimalAmount;

/**
 * One shared set of input rules for every ERP form (QA-004/006/007): strict decimals within decimal(18,4) and bounded entry dates.
 * Amounts: plain decimals, <= 14 integer digits, <= 2 decimals (percent/rates have their own precision).
 */
final class ErpRules
{
    public const MIN_DATE = '2000-01-01';

    /** Upper bound for entry dates: today + 1 year (fiscal years are created on demand; the Periods page shows 2000-2100). */
    public static function maxDate(): string
    {
        return now()->addYear()->toDateString();
    }

    /** Money amount; $positive = strictly > 0, else >= 0. */
    public static function money(bool $required = true, bool $positive = true): array
    {
        return [$required ? 'required' : 'nullable', 'numeric', new DecimalAmount(14, 2), $positive ? 'gt:0' : 'min:0', 'max:99999999999999.99'];
    }

    /** Percentage 0..100 with up to 4 decimals. */
    public static function percent(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'numeric', new DecimalAmount(3, 4), 'min:0', 'max:100'];
    }

    /** Positive rate / ratio with up to 8 decimals (exchange rates). */
    public static function rate(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'numeric', new DecimalAmount(8, 8), 'gt:0', 'max:99999999'];
    }

    /** Accounting / document date within the supported range (2000-01-01 .. today + 1 year). */
    public static function date(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'date', 'after_or_equal:'.self::MIN_DATE, 'before_or_equal:'.self::maxDate()];
    }

    /** Normalise a validated amount to a bcmath-safe string (scale 4). */
    public static function amount(mixed $v): string
    {
        $v = trim((string) ($v ?? '0'));

        return preg_match('/^\d+(\.\d+)?$/', $v) ? bcadd($v, '0', 4) : '0.0000';
    }
}
