<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use Illuminate\Support\Str;

enum DiscountScope: string
{
    use HasLabels;

    case ALL = 'all';
    case PRODUCTS = 'products';
    case CATEGORIES = 'categories';

    public function label(): string
    {
        return __('orders.enums.'.Str::snake('DiscountScope').'.'.$this->value);
    }
}
