<?php

namespace App\Events\Erp;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/** ERP hook: a carrier handed over the COD cash of a delivered order. */
class CarrierSettled
{
    use Dispatchable;

    public function __construct(public Order $order) {}
}
