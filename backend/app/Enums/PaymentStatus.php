<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use Illuminate\Support\Str;

enum PaymentStatus: string
{
    use HasLabels;

    case UNPAID = 'unpaid';
    case REVIEW = 'pending_review';
    case PARTIALLY_PAID = 'partially_paid'; // COD: prepaid shipping received, balance due on delivery
    case PAID = 'paid';
    case FAILED = 'failed';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return __('orders.enums.'.Str::snake('PaymentStatus').'.'.$this->value);
    }
}
