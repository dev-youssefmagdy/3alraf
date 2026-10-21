<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use Illuminate\Support\Str;

enum ProofStatus: string
{
    use HasLabels;

    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return __('orders.enums.'.Str::snake('ProofStatus').'.'.$this->value);
    }
}
