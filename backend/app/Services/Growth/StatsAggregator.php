<?php

namespace App\Services\Growth;

use App\Enums\OrderStatus;
use App\Models\GrowthDailyStat;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Builds growth_daily_stats for the CURRENT tenant (docs/CALCULATIONS.md "Growth system definitions"). Idempotent: rebuilding a day replaces its rows.
 * Contribution margin (per order) = (subtotal - discount) - cogs - packaging_cost - shipping_subsidy - (payment_fee - payment_fee_charged - cod_fee).
 * Orders with a missing cost (item unit_cost null/<=0, shipping_cost null) never enter the margin; they are counted in missing_* instead.
 */
class StatsAggregator
{
    public static function channel(Order $o): string
    {
        return $o->channel ?: ($o->affiliate_id ? 'affiliate' : 'direct');
    }

    /** Net share of a line that was NOT returned (order_items.returned_qty, approved/refunded returns): (quantity - returned_qty) / quantity. */
    public static function keptRatio($it): float
    {
        $q = max((int) $it->quantity, 1);

        return max($q - (int) $it->returned_qty, 0) / $q;
    }

    /**
     * Figures NET of partial returns (docs/CALCULATIONS.md): revenue and COGS of returned units are removed, return shipping cost is added to other costs.
     * @return array{revenue:float,cogs:float,other:float,cm:float,missing_cogs:bool,missing_shipping:bool}
     */
    public static function orderFigures(Order $o): array
    {
        $returnedRev = 0.0;
        $returnedCogs = 0.0;
        foreach ($o->items as $it) {
            $ret = min((int) $it->returned_qty, (int) $it->quantity);
            $returnedRev += ((float) $it->line_subtotal - (float) $it->line_discount) * (1 - self::keptRatio($it));
            $returnedCogs += (float) $it->unit_cost * $ret;
        }
        $returnShipping = $o->relationLoaded('returns') ? (float) $o->returns->filter(fn ($r) => in_array($r->status?->value ?? $r->status, ['approved', 'refunded'], true))->sum('return_shipping_cost') : 0.0;
        $revenue = (float) $o->subtotal - (float) $o->discount - round($returnedRev, 2);
        $cogs = (float) $o->cogs - round($returnedCogs, 2);
        $subsidy = max((float) $o->shipping_cost - (float) $o->shipping_net, 0);
        $fees = (float) $o->payment_fee - (float) $o->payment_fee_charged - (float) $o->cod_fee;
        $other = round((float) $o->packaging_cost + $subsidy + $fees + (float) $o->affiliate_commission + $returnShipping, 2);
        $mc = $o->items->contains(fn ($i) => $i->unit_cost === null || (float) $i->unit_cost <= 0);

        return ['revenue' => round($revenue, 2), 'cogs' => round($cogs, 2), 'other' => $other, 'cm' => round($revenue - $cogs - $other, 2), 'missing_cogs' => $mc, 'missing_shipping' => $o->shipping_cost === null];
    }

    public function rebuild(CarbonInterface $from, CarbonInterface $to): int
    {
        $rows = 0;
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            $rows += $this->rebuildDay($d->copy());
        }

        return $rows;
    }

    public function rebuildDay(CarbonInterface $day): int
    {
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();
        $acc = [];
        $blank = fn () => ['orders_placed' => 0, 'orders_completed' => 0, 'orders_cancelled' => 0, 'orders_returned' => 0, 'units' => 0, 'revenue' => 0.0, 'cogs' => 0.0, 'other_costs' => 0.0, 'contribution_margin' => 0.0, 'missing_cogs' => 0, 'missing_shipping' => 0];

        Order::with('items', 'returns')->whereBetween('placed_at', [$start, $end])->chunkById(500, function ($orders) use (&$acc, $blank) {
            foreach ($orders as $o) {
                $ch = self::channel($o);
                $k = "order|0|$ch";
                $acc[$k] ??= $blank();
                $acc[$k]['orders_placed']++;
                if ($o->status === OrderStatus::CANCELLED) {
                    $acc[$k]['orders_cancelled']++;
                } elseif ($o->status === OrderStatus::RETURNED) {
                    $acc[$k]['orders_returned']++;
                } elseif ($o->status === OrderStatus::DELIVERED) {
                    $f = self::orderFigures($o);
                    $acc[$k]['orders_completed']++;
                    $acc[$k]['revenue'] += $f['revenue'];
                    $acc[$k]['units'] += (int) $o->items->sum(fn ($i) => max((int) $i->quantity - (int) $i->returned_qty, 0));
                    $acc[$k]['missing_cogs'] += $f['missing_cogs'] ? 1 : 0;
                    $acc[$k]['missing_shipping'] += $f['missing_shipping'] ? 1 : 0;
                    if (! $f['missing_cogs'] && ! $f['missing_shipping']) {
                        $acc[$k]['cogs'] += $f['cogs'];
                        $acc[$k]['other_costs'] += $f['other'];
                        $acc[$k]['contribution_margin'] += $f['cm'];
                    }
                    // product rows: other costs allocated by the line's share of the order revenue
                    $gross = max((float) $o->items->sum('line_subtotal'), 0.01);
                    foreach ($o->items as $it) {
                        $pk = "product|{$it->product_id}|$ch";
                        $acc[$pk] ??= $blank();
                        $kept = self::keptRatio($it);
                        $keptQty = max((int) $it->quantity - (int) $it->returned_qty, 0);
                        $lineRev = ((float) $it->line_subtotal - (float) $it->line_discount) * $kept;
                        $lineCogs = (float) $it->unit_cost * $keptQty;
                        $share = ((float) $it->line_subtotal) / $gross;
                        $acc[$pk]['orders_completed']++;
                        $acc[$pk]['units'] += $keptQty;
                        $acc[$pk]['revenue'] += $lineRev;
                        if ($it->unit_cost === null || (float) $it->unit_cost <= 0 || $f['missing_shipping']) {
                            $acc[$pk]['missing_cogs'] += ($it->unit_cost === null || (float) $it->unit_cost <= 0) ? 1 : 0;
                            $acc[$pk]['missing_shipping'] += $f['missing_shipping'] ? 1 : 0;
                        } else {
                            $acc[$pk]['cogs'] += $lineCogs;
                            $acc[$pk]['other_costs'] += $f['other'] * $share;
                            $acc[$pk]['contribution_margin'] += $lineRev - $lineCogs - $f['other'] * $share;
                        }
                    }
                }
            }
        });

        DB::transaction(function () use ($acc, $day) {
            GrowthDailyStat::where('stat_date', $day->toDateString())->delete();
            foreach ($acc as $key => $v) {
                [$scope, $pid, $ch] = explode('|', $key);
                (new GrowthDailyStat)->forceFill(['stat_date' => $day->toDateString(), 'scope' => $scope, 'product_id' => (int) $pid, 'channel' => $ch] + array_map(fn ($x) => is_float($x) ? round($x, 2) : $x, $v))->save();
            }
        });

        return count($acc);
    }
}
