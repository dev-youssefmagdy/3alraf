<?php

namespace App\Services\Erp\Posting;

use App\Enums\AdjustmentType;
use App\Models\Erp\JournalEntry;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Support\Money;

/**
 * Stock documents valued by the stock ledger (weighted average, item unit_cost snapshot).
 *  adjustment decrease/damage/loss : Dr Inventory loss / Cr Inventory ; increase (found/count) : Dr Inventory / Cr Inventory loss
 *  transfer sent : Dr Inventory in transit / Cr Inventory (source branch) ; received : Dr Inventory (target branch) / Cr Inventory in transit
 *  -> consolidated inventory and profit are untouched by a transfer.
 */
class StockPoster extends Poster
{
    private function value($items): string
    {
        return $items->reduce(fn ($c, $i) => Money::add($c, Money::mul((string) $i->unit_cost, $i->quantity)), '0');
    }

    public function adjusted(StockAdjustment $a): ?JournalEntry
    {
        $v = $this->value($a->items()->get());
        $b = $a->branch_id;
        $lines = $a->type === AdjustmentType::INCREASE
            ? [$this->dr('inventory', $v, $b, $a->number), $this->cr('inventory_loss', $v, $b, $a->number)]
            : [$this->dr('inventory_loss', $v, $b, $a->number), $this->cr('inventory', $v, $b, $a->number)];

        return $this->post('adj:'.$a->id, $this->today(), 'Stock adjustment '.$a->number, 'stock_adjustment', $a->id, $lines, $b);
    }

    public function transferSent(StockTransfer $t): ?JournalEntry
    {
        $v = $this->value($t->items()->get());

        return $this->post('transfer:'.$t->id.':out', $this->today(), 'Transfer '.$t->number.' sent', 'stock_transfer', $t->id,
            [$this->dr('inventory_transit', $v, $t->from_branch_id, $t->number), $this->cr('inventory', $v, $t->from_branch_id, $t->number)], $t->from_branch_id);
    }

    public function transferReceived(StockTransfer $t): ?JournalEntry
    {
        $v = $this->value($t->items()->get());

        return $this->post('transfer:'.$t->id.':in', $this->today(), 'Transfer '.$t->number.' received', 'stock_transfer', $t->id,
            [$this->dr('inventory', $v, $t->to_branch_id, $t->number), $this->cr('inventory_transit', $v, $t->from_branch_id, $t->number)], $t->to_branch_id);
    }
}
