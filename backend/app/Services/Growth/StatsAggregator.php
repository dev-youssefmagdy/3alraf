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
        return $o->affiliate_id ? 'affiliate' : 'direct';
    }

    /** @return array{revenue:float,other:float,cm:float,missing_cogs:bool,missing_shipping:bool} */
    public static function orderFigures(Order $o): array
    {
        $revenue = (float) $o->subtotal - (float) $o->discount;
        $subsidy = max((float) $o->shipping_cost - (float) $o->shipping_net, 0);
        $fees = (float) $o->payment_fee - (float) $o->payment_fee_charged - (float) $o->cod_fee;
        $other = round((float) $o->packaging_cost + $subsidy + $fees + (float) $o->affiliate_commission, 2);
        $mc = $o->items->contains(fn ($i) => $i->unit_cost === null || (float) $i->unit_cost <= 0);

        return ['revenue' => round($revenue, 2), 'other' => $other, 'cm' => round($revenue - (float) $o->cogs - $other, 2), 'missing_cogs' => $mc, 'missing_shipping' => $o->shipping_cost === null];
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

        Order::with('items')->whereBetween('placed_at', [$start, $end])->chunkById(500, function ($orders) use (&$acc, $blank) {
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
                    $acc[$k]['units'] += (int) $o->items->sum('quantity');
                    $acc[$k]['missing_cogs'] += $f['missing_cogs'] ? 1 : 0;
                    $acc[$k]['missing_shipping'] += $f['missing_shipping'] ? 1 : 0;
                    if (! $f['missing_cogs'] && ! $f['missing_shipping']) {
                        $acc[$k]['cogs'] += (float) $o->cogs;
                        $acc[$k]['other_costs'] += $f['other'];
                        $acc[$k]['contribution_margin'] += $f['cm'];
                    }
                    // product rows: other costs allocated by the line's share of the order revenue
                    $gross = max((float) $o->items->sum('line_subtotal'), 0.01);
                    foreach ($o->items as $it) {
                        $pk = "product|{$it->product_id}|$ch";
                        $acc[$pk] ??= $blank();
                        $lineRev = (float) $it->line_subtotal - (float) $it->line_discount;
                        $lineCogs = (float) $it->unit_cost * (int) $it->quantity;
                        $share = ((float) $it->line_subtotal) / $gross;
                        $acc[$pk]['orders_completed']++;
                        $acc[$pk]['units'] += (int) $it->quantity;
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
