<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use Illuminate\Support\Str;

enum PaymentStatus: string
{
    use HasLabels;

    case UNPAID = 'unpaid';
    case REVIEW = 'pending_review';
    case PAID = 'paid';
    case FAILED = 'failed';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return __('orders.enums.'.Str::snake('PaymentStatus').'.'.$this->value);
    }
}
