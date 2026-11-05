<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Strict plain-decimal input (QA-004/005/006): digits with an optional fraction, no sign, exponent, hex, spaces or thousands separators.
 * Bounds fit the ERP money columns decimal(18,4): at most $intDigits integer digits and $scale decimals.
 */
class DecimalAmount implements ValidationRule
{
    public function __construct(private int $intDigits = 14, private int $scale = 2) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_int($value)) {
            $value = (string) $value;
        } elseif (is_float($value)) {
            $value = rtrim(rtrim(number_format($value, $this->scale + 1, '.', ''), '0'), '.');
        }
        if (! is_string($value) || ! preg_match('/^\d{1,'.$this->intDigits.'}(\.\d{1,'.$this->scale.'})?$/', $value)) {
            $fail('erp_acc.validation.decimal')->translate(['digits' => $this->intDigits, 'scale' => $this->scale]);
        }
    }
}
