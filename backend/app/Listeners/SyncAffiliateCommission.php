<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Services\Affiliates\AffiliateCommissionService;

/** Cancelled / returned orders void their affiliate commission. */
class SyncAffiliateCommission
{
    public function __construct(private AffiliateCommissionService $commissions) {}

    public function handle(OrderStatusChanged $event): void
    {
        $this->commissions->syncOrder($event->order);
    }
}
