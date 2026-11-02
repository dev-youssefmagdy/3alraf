<?php

namespace App\Events\Erp;

use App\Models\StockTransfer;
use Illuminate\Foundation\Events\Dispatchable;

/** $stage: sent | received. */
class StockTransferMoved
{
    use Dispatchable;

    public function __construct(public StockTransfer $transfer, public string $stage) {}
}
