<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use Illuminate\Support\Str;

enum DiscountType: string
{
    use HasLabels;

    case PERCENT = 'percent';
    case FIXED = 'fixed';

    public function label(): string
    {
        return __('orders.enums.'.Str::snake('DiscountType').'.'.$this->value);
    }
}
