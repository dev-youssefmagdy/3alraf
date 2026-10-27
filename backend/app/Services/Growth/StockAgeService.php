<?php

namespace App\Services\Growth;

use App\Enums\StockMovementType;
use App\Models\StockMovement;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Stock age by FIFO layering over the append-only stock ledger (no schema change). Incoming layers = purchase_in / return_in / adjustment_in; outgoing
 * (sale_out, adjustment_out) consumes the OLDEST layers first. Transfers move stock between branches and do not change the tenant total, so they are ignored.
 * The surviving layers are the NEWEST receipts that add up to the current on-hand; on-hand that no receipt explains (opening stock) is `unknown_qty` and
 * is never given an invented age. See docs/CALCULATIONS.md "Stock age (FIFO)".
 */
class StockAgeService
{
    private const IN = [StockMovementType::PURCHASE_IN, StockMovementType::RETURN_IN, StockMovementType::ADJUSTMENT_IN];

    private const OUT = [StockMovementType::SALE_OUT, StockMovementType::ADJUSTMENT_OUT];

    /**
     * @param  Collection<int,array{type:StockMovementType,quantity:int,unit_cost:?float,at:CarbonInterface}>  $movements  of ONE product, any order
     * @return array{oldest_age_days:?int,weighted_age_days:?float,on_hand:int,layers:array<int,array{qty:int,age_days:int,unit_cost:?float}>,unknown_qty:int}
     */
    public static function layers(Collection $movements, int $onHand, CarbonInterface $now): array
    {
        $layers = [];
        $out = 0;
        foreach ($movements->sortBy('at')->values() as $m) {
            if (in_array($m['type'], self::IN, true)) {
                $layers[] = ['qty' => (int) $m['quantity'], 'at' => $m['at'], 'unit_cost' => $m['unit_cost']];
            } elseif (in_array($m['type'], self::OUT, true)) {
                $out += (int) $m['quantity'];
            }
        }
        foreach ($layers as &$l) { // FIFO: outgoing eats the oldest layers first
            $take = min($l['qty'], $out);
            $l['qty'] -= $take;
            $out -= $take;
        }
        unset($l);
        $layers = array_values(array_filter($layers, fn ($l) => $l['qty'] > 0));
        // the ledger can disagree with the real on-hand (e.g. opening stock): keep the newest layers that fit on-hand
        $total = array_sum(array_column($layers, 'qty'));
        while ($layers && $total > $onHand) {
            $drop = min($layers[0]['qty'], $total - $onHand);
            $layers[0]['qty'] -= $drop;
            $total -= $drop;
            if ($layers[0]['qty'] <= 0) {
                array_shift($layers);
            }
        }
        $out = [];
        foreach ($layers as $l) {
            $out[] = ['qty' => $l['qty'], 'age_days' => max(0, (int) $l['at']->copy()->startOfDay()->diffInDays($now->copy()->startOfDay(), true)), 'unit_cost' => $l['unit_cost']];
        }
        $units = array_sum(array_column($out, 'qty'));

        return ['oldest_age_days' => $out ? $out[0]['age_days'] : null,
            'weighted_age_days' => $units > 0 ? round(array_sum(array_map(fn ($l) => $l['qty'] * $l['age_days'], $out)) / $units, 1) : null,
            'on_hand' => $onHand, 'layers' => $out, 'unknown_qty' => max(0, $onHand - $units)];
    }

    /** Value (qty x layer cost, else `$fallbackCost`) of the units older than `$days`. */
    public static function valueOlderThan(array $layers, int $days, float $fallbackCost = 0.0): float
    {
        $v = 0.0;
        foreach ($layers as $l) {
            if ($l['age_days'] > $days) {
                $v += $l['qty'] * (float) ($l['unit_cost'] ?: $fallbackCost);
            }
        }

        return round($v, 2);
    }

    /**
     * Bulk analysis for the inventory engine: one ledger query for all products.
     *
     * @param  array<int,int>  $onHand  product_id => on-hand
     * @return array<int,array> product_id => layers() result
     */
    public function forProducts(array $onHand, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $ids = array_keys(array_filter($onHand, fn ($q) => $q > 0));
        $by = [];
        if ($ids) {
            StockMovement::whereIn('product_id', $ids)->whereIn('type', array_map(fn ($t) => $t->value, [...self::IN, ...self::OUT]))->orderBy('created_at')->orderBy('id')
                ->get(['product_id', 'type', 'quantity', 'unit_cost', 'created_at'])
                ->each(function ($m) use (&$by) {
                    $by[$m->product_id][] = ['type' => $m->type, 'quantity' => (int) $m->quantity, 'unit_cost' => $m->unit_cost !== null ? (float) $m->unit_cost : null, 'at' => $m->created_at];
                });
        }
        $res = [];
        foreach ($onHand as $pid => $q) {
            $res[$pid] = self::layers(collect($by[$pid] ?? []), (int) $q, $now);
        }

        return $res;
    }
}
