<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use Illuminate\Support\Str;

enum ReturnStatus: string
{
    use HasLabels;

    case REQUESTED = 'requested';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return __('orders.enums.'.Str::snake('ReturnStatus').'.'.$this->value);
    }
}
