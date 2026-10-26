<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** kind: proof_approved | proof_rejected | paid (online gateway settled) */
class OrderPaymentUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order, public string $kind) {}
}
