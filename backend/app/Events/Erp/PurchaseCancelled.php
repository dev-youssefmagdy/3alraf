<?php

namespace App\Events\Erp;

use Illuminate\Foundation\Events\Dispatchable;

/** ERP hook: fired by the business service inside its own transaction; the ledger listener posts atomically with it. */
class PurchaseCancelled
{
    use Dispatchable;

    public function __construct(public \App\Models\PurchaseOrder $purchase) {}
}
