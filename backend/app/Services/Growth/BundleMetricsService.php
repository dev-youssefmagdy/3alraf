<?php

namespace App\Services\Growth;

use App\Models\Bundle;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Orders\BundleResolver;
use Carbon\CarbonInterface;

/**
 * Post-launch bundle performance (spec 4 "measure performance"): AOV uplift (orders with the bundle vs without), contribution margin
 * (MetricsService::bundles, allocated), sell-through, and bundle vs the same components sold individually. Delivered orders only.
 */
class BundleMetricsService
{
    public function __construct(private MetricsService $metrics, private BundleResolver $resolver) {}

    public function report(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = collect($this->metrics->bundles($from, $to))->keyBy('bundle_id');
        $range = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $delivered = fn () => Order::where('status', 'delivered')->whereBetween('placed_at', $range);
        $out = [];
        foreach (Bundle::with('items')->orderByDesc('id')->limit(100)->get() as $b) {
            $r = $rows[$b->id] ?? null;
            $perBundle = max((int) $b->items->sum('quantity'), 1);
            $units = $r ? intdiv((int) $r['units'], $perBundle) : 0;
            $withIds = OrderItem::where('bundle_id', $b->id)->pluck('order_id')->unique();
            $with = $delivered()->whereIn('id', $withIds)->avg('total');
            $without = $delivered()->whereNotIn('id', $withIds)->avg('total');
            $avail = $this->availableNow($b);
            $individual = OrderItem::whereNull('bundle_id')->whereIn('product_id', $b->items->pluck('product_id'))
                ->whereHas('order', fn ($q) => $q->where('status', 'delivered')->whereBetween('placed_at', $range))->sum('quantity');
            $out[] = [
                'bundle_id' => $b->id, 'name' => (string) $b->name, 'is_active' => $b->is_active, 'orders' => $r['orders'] ?? 0, 'units' => $units, 'revenue' => $r['revenue'] ?? 0.0,
                'line_margin' => $r['line_margin'] ?? MetricsService::m(null, 'insufficient', ['no_orders']),
                'aov_with' => $with === null ? null : round((float) $with, 2), 'aov_without' => $without === null ? null : round((float) $without, 2),
                'aov_uplift_percent' => ($with !== null && $without > 0) ? round(((float) $with - (float) $without) / (float) $without * 100, 1) : null,
                'sell_through' => ($units + (int) $avail) > 0 ? round($units / ($units + (int) $avail) * 100, 1) : null,
                'individual_component_units' => (int) $individual, 'bundle_component_units' => $units * $perBundle,
            ];
        }

        return $out;
    }

    private function availableNow(Bundle $b): ?int
    {
        try {
            $c = $this->resolver->components($b);

            return $c ? $this->resolver->available($b, $c) : 0;
        } catch (\Throwable) {
            return null;
        }
    }
}
