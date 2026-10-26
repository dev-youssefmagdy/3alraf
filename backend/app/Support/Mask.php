<?php

namespace App\Support;

/** Masks personal data before it is written to the audit log (enough to recognise, not enough to leak). */
class Mask
{
    public static function value(?string $v): ?string
    {
        if ($v === null || $v === '') {
            return $v;
        }
        if (str_contains($v, '@')) {
            [$u, $d] = explode('@', $v, 2);

            return mb_substr($u, 0, 1).'***@'.$d;
        }

        return mb_strlen($v) <= 4 ? '***' : mb_substr($v, 0, 2).str_repeat('*', max(mb_strlen($v) - 4, 1)).mb_substr($v, -2);
    }
}
