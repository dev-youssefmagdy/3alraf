<?php

namespace App\Services\Admin;

use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\TenantProduct;
use App\Models\TenantProductVariant;
use Illuminate\Support\Collection;

/**
 * Listed products / variants whose available quantity (on hand − reserved, active branches) is at or below their alert quantity.
 * Alert = variant override, else the product's, else DEFAULT_ALERT. Items that were never stocked (no stock row) are ignored.
 */
class LowStockService
{
    public const DEFAULT_ALERT = 5;

    /** @return Collection<int,array{tp:TenantProduct,variant:?ProductVariant,available:int,alert:int}> lowest stock first */
    public function rows(): Collection
    {
        $levels = StockLevel::query()->whereHas('branch', fn ($b) => $b->where('is_active', true))
            ->groupBy('product_id', 'product_variant_id')->selectRaw('product_id, product_variant_id, SUM(on_hand - reserved) a')->get();
        if ($levels->isEmpty()) {
            return collect();
        }
        $tps = TenantProduct::query()->where('is_active', true)->with('product')->whereIn('product_id', $levels->pluck('product_id'))->get()->keyBy('product_id');
        $tvs = TenantProductVariant::query()->whereIn('tenant_product_id', $tps->pluck('id'))->get()->keyBy(fn ($tv) => $tv->tenant_product_id.':'.$tv->product_variant_id);
        $variants = ProductVariant::query()->with('values')->whereIn('id', $levels->pluck('product_variant_id')->filter())->get()->keyBy('id');

        return $levels->map(function ($l) use ($tps, $tvs, $variants) {
            $tp = $tps->get($l->product_id);
            if (! $tp || ! $tp->product) {
                return null;
            }
            $vid = (int) $l->product_variant_id;
            $tv = $vid ? $tvs->get($tp->id.':'.$vid) : null;
            $variant = $vid ? $variants->get($vid) : null;
            if ($vid && (! $variant || $variant->trashed() || ($tv && ! $tv->is_active))) {
                return null;
            }
            $alert = (int) ($tv?->min_stock_alert_qty ?? $tp->min_stock_alert_qty ?? self::DEFAULT_ALERT);
            $available = max(0, (int) $l->a);

            return $available <= $alert ? compact('tp', 'variant', 'available', 'alert') : null;
        })->filter()->sortBy('available')->values();
    }
}
