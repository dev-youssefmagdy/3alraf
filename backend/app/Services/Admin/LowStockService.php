<?php

namespace App\Services\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use Illuminate\Support\Collection;

/**
 * Listed products / variants whose available quantity (on hand − reserved, active branches) is at or below their alert quantity.
 * Alert = variant override, else the product's, else DEFAULT_ALERT. Items that were never stocked (no stock row) are ignored.
 */
class LowStockService
{
    public const DEFAULT_ALERT = 5;

    /** @return Collection<int,array{product:Product,variant:?ProductVariant,available:int,alert:int}> lowest stock first */
    public function rows(): Collection
    {
        $levels = StockLevel::query()->whereHas('branch', fn ($b) => $b->where('is_active', true))
            ->groupBy('product_id', 'product_variant_id')->selectRaw('product_id, product_variant_id, SUM(on_hand - reserved) a')->get();
        if ($levels->isEmpty()) {
            return collect();
        }
        $products = Product::query()->where('is_active', true)->whereNotNull('price')->whereIn('id', $levels->pluck('product_id'))->get()->keyBy('id');
        $variants = ProductVariant::query()->with('values')->whereIn('id', $levels->pluck('product_variant_id')->filter())->get()->keyBy('id');

        return $levels->map(function ($l) use ($products, $variants) {
            $product = $products->get($l->product_id);
            if (! $product) {
                return null;
            }
            $vid = (int) $l->product_variant_id;
            $variant = $vid ? $variants->get($vid) : null;
            if ($vid && (! $variant || $variant->trashed() || ! $variant->is_active)) {
                return null;
            }
            $alert = (int) ($variant?->min_stock_alert_qty ?? $product->min_stock_alert_qty ?? self::DEFAULT_ALERT);
            $available = max(0, (int) $l->a);

            return $available <= $alert ? compact('product', 'variant', 'available', 'alert') : null;
        })->filter()->sortBy('available')->values();
    }
}
