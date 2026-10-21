<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use Illuminate\Support\Str;

enum PayoutStatus: string
{
    use HasLabels;

    case REQUESTED = 'requested';
    case PAID = 'paid';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return __('growth.enums.'.Str::snake('PayoutStatus').'.'.$this->value);
    }
}
