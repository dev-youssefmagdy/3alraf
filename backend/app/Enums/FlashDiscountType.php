<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use Illuminate\Support\Str;

enum FlashDiscountType: string
{
    use HasLabels;

    case PRICE = 'price';
    case PERCENT = 'percent';

    public function label(): string
    {
        return __('orders.enums.'.Str::snake('FlashDiscountType').'.'.$this->value);
    }
}
