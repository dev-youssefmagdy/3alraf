<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use Illuminate\Support\Str;

enum CommissionStatus: string
{
    use HasLabels;

    case PENDING = 'pending';
    case APPROVED = 'approved';
    case PAID = 'paid';
    case VOIDED = 'voided';

    public function label(): string
    {
        return __('growth.enums.'.Str::snake('CommissionStatus').'.'.$this->value);
    }
}
