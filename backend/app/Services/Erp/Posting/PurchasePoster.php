<?php

namespace App\Services\Erp\Posting;

use App\Models\Erp\JournalEntry;
use App\Models\PurchaseOrder;
use App\Support\Money;

/**
 * Purchases. Receive : Dr Inventory (items, = the stock movements' valuation) + Purchase-related expenses (shipping/other landed costs,
 * which the stock ledger does NOT capitalise - CALCULATIONS.md §Stock) / Cr Accounts payable (party = supplier).
 * Supplier payment : Dr Accounts payable / Cr cash-like account. Cancel: only drafts can be cancelled, so there is nothing to reverse
 * (defensive reversal if an entry exists).
 */
class PurchasePoster extends Poster
{
    public function received(PurchaseOrder $po): ?JournalEntry
    {
        $items = Money::round((string) $po->items_total);
        $landed = Money::add((string) $po->shipping_cost, (string) $po->other_cost);
        $total = Money::add($items, $landed);
        $b = $po->branch_id;
        $lines = [
            $this->dr('inventory', $items, $b, $po->number), $this->dr('purchase_expenses', $landed, $b, $po->number),
            $this->cr('payable', $total, $b, $po->number, 'supplier', $po->supplier_id),
        ];

        return $this->post('po:'.$po->id.':receive', $this->today(), 'Purchase '.$po->number.' received', 'purchase_order', $po->id, $lines, $b);
    }

    public function cancelled(PurchaseOrder $po): int
    {
        return $this->reverseLive('purchase_order', $po->id, ['po:'.$po->id.':receive'], 'purchase cancelled');
    }

    /** Pay (part of) a supplier bill. $ref makes the posting idempotent (e.g. a payment id / receipt number). */
    public function payment(PurchaseOrder $po, string $amount, string $ref, string $from = 'cash', ?string $date = null): ?JournalEntry
    {
        $amount = Money::round($amount);

        return $this->post('po:'.$po->id.':pay:'.$ref, $date ?? $this->today(), 'Payment for purchase '.$po->number, 'purchase_order', $po->id, [
            $this->dr('payable', $amount, $po->branch_id, $po->number, 'supplier', $po->supplier_id), $this->cr($from, $amount, $po->branch_id, $po->number),
        ], $po->branch_id);
    }
}
