<?php

namespace App\Events;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

class OrderStatusChanged
{
    use Dispatchable;

    public function __construct(public Order $order, public ?OrderStatus $from, public OrderStatus $to) {}
}
