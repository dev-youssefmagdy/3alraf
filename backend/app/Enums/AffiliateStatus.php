<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use Illuminate\Support\Str;

enum AffiliateStatus: string
{
    use HasLabels;

    case PENDING = 'pending';
    case APPROVED = 'approved';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return __('growth.enums.'.Str::snake('AffiliateStatus').'.'.$this->value);
    }
}
