<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;

/** Marks delivered COD orders as settled by the carrier (carrier handed over cash minus its fee). Mirrors the cod-settlement report window (incl. its branch filter). */
class CarrierSettlementService
{
    /** @return int number of orders marked settled */
    public function settle(?int $shippingCompanyId, string $from, string $to, ?int $branchId = null): int
    {
        $count = 0;
        Order::query()->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->where('status', OrderStatus::DELIVERED->value)->where('cod_amount', '>', 0)->whereNull('carrier_settled_at')
            ->where('shipping_company_id', $shippingCompanyId)->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->each(function (Order $o) use (&$count) { // per model so the audit log records each order
                $o->forceFill(['carrier_settled_at' => now()])->save();
                $count++;
            });

        return $count;
    }
}
