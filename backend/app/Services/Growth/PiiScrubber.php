<?php

namespace App\Services\Growth;

/** Defence in depth: PII keys are dropped and email/phone patterns masked in any payload that leaves Laravel. */
class PiiScrubber
{
    public static function scrub(array $data): array
    {
        $fields = (array) config('growth.pii.mask_fields');
        $out = [];
        foreach ($data as $k => $v) {
            if (is_string($k) && in_array(strtolower($k), $fields, true)) {
                continue;
            }
            $out[$k] = is_array($v) ? self::scrub($v) : (is_string($v) ? self::text($v) : $v);
        }

        return $out;
    }

    public static function text(string $s): string
    {
        foreach ((array) config('growth.pii.mask_patterns') as $label => $re) {
            $s = preg_replace($re, '['.$label.']', $s) ?? $s;
        }

        return $s;
    }
}
