<?php

namespace App\Services\Erp\Posting;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Erp\Equity\ErpSettings;

/**
 * When an order's revenue, VAT and COGS are recognised in the ledger (docs/CALCULATIONS.md §ERP revenue recognition).
 * ERP setting `revenue_recognition`:
 *   shipped   (default) control passes when the parcel leaves the warehouse; COGS = the value the stock ledger removed (sale_out)
 *   delivered control passes on delivery; between shipment and delivery the shipped cost sits in "Goods out for delivery"
 *   confirmed legacy: at admin confirmation (mirrors the Net Profit report); COGS trued up to the shipped cost at shipment
 */
class RevenueRecognition
{
    public const POLICIES = ['shipped', 'delivered', 'confirmed'];

    public static function policy(): string
    {
        $p = (string) app(ErpSettings::class)->get('revenue_recognition');

        return in_array($p, self::POLICIES, true) ? $p : 'shipped';
    }

    /** @return array<int,OrderStatus> statuses in which an order's revenue is recognised */
    public static function statuses(?string $policy = null): array
    {
        return match ($policy ?? self::policy()) {
            'confirmed' => OrderStatus::salesStatuses(),
            'delivered' => [OrderStatus::DELIVERED],
            default => [OrderStatus::SHIPPED, OrderStatus::DELIVERED],
        };
    }

    /** @return array<int,string> */
    public static function values(?string $policy = null): array
    {
        return array_map(fn (OrderStatus $s) => $s->value, self::statuses($policy));
    }

    public static function recognised(Order $o): bool
    {
        return in_array($o->status, self::statuses(), true);
    }

    /** Sales-status orders (confirmed..delivered) whose revenue is NOT yet in the ledger under the current policy. @return array<int,string> */
    public static function pendingValues(?string $policy = null): array
    {
        return array_values(array_diff(array_map(fn ($s) => $s->value, OrderStatus::salesStatuses()), self::values($policy)));
    }
}
