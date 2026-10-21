<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use Illuminate\Support\Str;

enum ProductListType: string
{
    use HasLabels;

    case RECOMMENDED = 'recommended';
    case MOST_SOLD = 'most_sold';
    case MOST_REVIEWED = 'most_reviewed';

    public function label(): string
    {
        return __('growth.enums.'.Str::snake('ProductListType').'.'.$this->value);
    }
}
