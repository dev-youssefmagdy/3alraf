<?php

namespace App\Services\Storefront;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\TenantProduct;
use App\Models\TenantProductVariant;
use App\Services\Orders\PriceResolver;
use App\Support\Money;

/** Variant picker payload for the product page: option groups + one row per sellable variant with final price, stock, barcode. */
class ProductPageData
{
    public function __construct(private PriceResolver $prices) {}

    /** @return array{options:array,variants:array,variable:bool} */
    public function build(Product $product, TenantProduct $tp): array
    {
        $backorder = (bool) (current_tenant()?->settings['allow_backorder'] ?? false);
        $stock = StockLevel::query()->where('product_id', $product->id)->whereHas('branch', fn ($b) => $b->where('is_active', true))
            ->groupBy('product_variant_id')->selectRaw('product_variant_id, SUM(on_hand - reserved) a')->pluck('a', 'product_variant_id');
        $tvs = TenantProductVariant::where('tenant_product_id', $tp->id)->get()->keyBy('product_variant_id');
        $productImage = $product->images->first()?->url;

        $rows = [];
        $variants = $product->isVariable() ? $product->variants->where('is_active', true) : collect([null]);
        foreach ($variants as $v) {
            $vid = $v?->id ?? 0;
            $tv = $tvs->get($vid);
            if ($tv && ! $tv->is_active) {
                continue;
            }
            $src = ($tv && $tv->price !== null) ? $tv : $tp;
            $regular = (string) $src->price;
            $final = $src->sale_price !== null ? (string) $src->sale_price : $regular;
            $flash = $this->prices->flashItem($product->id, $vid);
            $flashEnds = null;
            if ($flash) {
                $final = $this->prices->flashPrice($flash, $final);
                $flashEnds = $flash->flashSale->ends_at->toIso8601String();
            }
            $available = $backorder ? PHP_INT_MAX : max(0, (int) ($stock[$vid] ?? 0));
            $rows[] = [
                'id' => $vid, 'values' => $v ? $v->values->pluck('id')->all() : [], 'price' => Money::round($final),
                'old' => bccomp($final, $regular, 2) < 0 ? Money::round($regular) : null, 'flash_ends' => $flashEnds,
                'stock' => $available === PHP_INT_MAX ? null : $available, 'in_stock' => $available > 0,
                'label' => $v?->label(), 'alert' => $tv?->min_stock_alert_qty ?? $tp->min_stock_alert_qty ?? 5, 'barcode' => $v?->barcode ?? $product->barcode, 'sku' => $v?->sku ?? $product->sku,
                'image' => $v?->images->first()?->url ?? $productImage,
            ];
        }
        $options = [];
        if ($product->isVariable()) {
            $byAttr = $product->variants->where('is_active', true)->flatMap->values->unique('id')->groupBy('attribute_id');
            $attrs = \App\Models\Attribute::whereIn('id', $byAttr->keys())->orderBy('sort_order')->get();
            foreach ($attrs as $a) {
                $options[] = ['id' => $a->id, 'name' => $a->name, 'values' => $byAttr[$a->id]->sortBy('sort_order')->map(fn ($x) => ['id' => $x->id, 'label' => $x->value, 'color' => $x->color_hex])->values()->all()];
            }
        }

        return ['options' => $options, 'variants' => $rows, 'variable' => $product->isVariable(), 'alert' => $tp->min_stock_alert_qty ?? 5];
    }
}
