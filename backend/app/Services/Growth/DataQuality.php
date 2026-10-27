<?php

namespace App\Services\Growth;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TenantProduct;

/**
 * Honest-metrics checks for the current tenant. Cost columns stay NOT NULL, so 0 means "unknown" (documented in CALCULATIONS.md).
 * Each issue: {key, count, ids[], severity}. Surfaced in the brief (data_quality[]) and as an admin warning list.
 */
class DataQuality
{
    public function issues(int $days = 30): array
    {
        $since = now()->subDays($days)->startOfDay();
        $issues = [];

        $products = TenantProduct::where('is_active', true)->where(fn ($q) => $q->whereNull('cost')->orWhere('cost', '<=', 0));
        if (($n = (clone $products)->count()) > 0) {
            $issues[] = ['key' => 'product_cost_missing', 'count' => $n, 'ids' => (clone $products)->orderBy('id')->limit(10)->pluck('product_id')->all(), 'severity' => 'high'];
        }
        $lines = OrderItem::where(fn ($q) => $q->whereNull('unit_cost')->orWhere('unit_cost', '<=', 0))
            ->whereHas('order', fn ($q) => $q->where('status', 'delivered')->where('placed_at', '>=', $since));
        if (($n = (clone $lines)->count()) > 0) {
            $issues[] = ['key' => 'order_line_cost_missing', 'count' => $n, 'ids' => (clone $lines)->distinct()->limit(10)->pluck('product_id')->all(), 'severity' => 'high'];
        }
        // A carrier fee is expected when the customer paid for shipping (shipping_net > 0) but the stored shipping cost is 0/null.
        $ship = Order::where('status', 'delivered')->where('placed_at', '>=', $since)->where('shipping_net', '>', 0)->where(fn ($q) => $q->whereNull('shipping_cost')->orWhere('shipping_cost', '<=', 0));
        if (($n = (clone $ship)->count()) > 0) {
            $issues[] = ['key' => 'shipping_cost_missing', 'count' => $n, 'ids' => (clone $ship)->orderByDesc('id')->limit(10)->pluck('id')->all(), 'severity' => 'medium'];
        }

        return $issues;
    }
}
