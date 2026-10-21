<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use Illuminate\Support\Str;

enum ReviewStatus: string
{
    use HasLabels;

    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return __('growth.enums.'.Str::snake('ReviewStatus').'.'.$this->value);
    }
}
