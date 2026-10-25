<?php

namespace App\Services\Orders;

use App\Enums\FlashDiscountType;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TenantProduct;
use App\Models\TenantProductVariant;
use App\Support\Money;

/**
 * unit_price resolution (docs/CALCULATIONS.md "Definitions" + "Flash sale"):
 * flash-sale price if active and quantity available, else tenant sale_price if set, else price.
 */
class PriceResolver
{
    /**
     * Sellable item snapshot for the current tenant, or null when not purchasable.
     *
     * @return array{product:Product,variant:?ProductVariant,tenant_product:TenantProduct,price:string,cost:string,name:string,sku:?string,barcode:?string}|null
     */
    public function item(int $productId, int $variantId = 0): ?array
    {
        $tp = TenantProduct::with('product.variants.values')->where('product_id', $productId)->where('is_active', true)->first();
        $product = $tp?->product;
        if (! $product || $product->trashed() || ! $product->is_active) {
            return null;
        }

        $variant = null;
        $tv = null;
        if ($product->isVariable()) {
            $variant = $product->variants->where('is_active', true)->firstWhere('id', $variantId);
            if (! $variant) {
                return null;
            }
            $tv = TenantProductVariant::where('tenant_product_id', $tp->id)->where('product_variant_id', $variant->id)->first();
            if ($tv && ! $tv->is_active) {
                return null;
            }
        } elseif ($variantId !== 0) {
            return null;
        }

        $src = ($tv && $tv->price !== null) ? $tv : $tp;
        $price = $src->sale_price !== null ? (string) $src->sale_price : (string) $src->price;
        $cost = ($tv && $tv->cost !== null) ? (string) $tv->cost : (string) ($tp->cost ?? '0');

        return [
            'product' => $product, 'variant' => $variant, 'tenant_product' => $tp, 'price' => Money::round($price), 'cost' => Money::round($cost),
            'name' => $product->name.($variant ? ' - '.$variant->label() : ''),
            'sku' => $variant?->sku ?? $product->sku, 'barcode' => $variant?->barcode ?? $product->barcode,
        ];
    }

    /**
     * Running flash-sale item for product/variant (exact variant first, then product-wide), with units remaining
     * after $consumed (flash units already granted per flash item id earlier in the same quote).
     */
    public function flashItem(int $productId, int $variantId, bool $lock = false, array $consumed = []): ?FlashSaleItem
    {
        $q = FlashSaleItem::query()->where('product_id', $productId)->whereIn('product_variant_id', array_unique([$variantId, 0]))
            ->whereHas('flashSale', fn ($f) => $f->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>=', now()))
            ->orderByDesc('product_variant_id');
        if ($lock) {
            $q->lockForUpdate();
        }

        return $q->get()->first(fn (FlashSaleItem $i) => $this->left($i, $consumed) === null || $this->left($i, $consumed) > 0);
    }

    /** Flash units still available for this quote: remaining − consumed by earlier lines (null = unlimited). */
    private function left(FlashSaleItem $i, array $consumed): ?int
    {
        $rem = $i->remaining();

        return $rem === null ? null : max(0, $rem - ($consumed[$i->id] ?? 0));
    }

    public function flashPrice(FlashSaleItem $item, string $regular): string
    {
        $p = $item->discount_type === FlashDiscountType::PERCENT
            ? Money::sub($regular, Money::percent($regular, (string) $item->value))
            : Money::round((string) $item->value);

        return Money::min($p, $regular);
    }

    /**
     * Split $qty into priced segments: units within the flash-sale remaining quantity use the flash price, the rest the regular price.
     * $consumed (flash item id => units) is shared by all lines of ONE quote and updated here, so a product-wide item used by
     * several variant lines never grants more than its remaining units in total (CALCULATIONS.md §Flash sale).
     *
     * @return array<int,array{unit_price:string,qty:int,flash:?FlashSaleItem}>
     */
    public function segments(array $item, int $qty, bool $lock = false, array &$consumed = []): array
    {
        $variantId = $item['variant']?->id ?? 0;
        $flash = $this->flashItem($item['product']->id, $variantId, $lock, $consumed);
        if (! $flash) {
            return [['unit_price' => $item['price'], 'qty' => $qty, 'flash' => null]];
        }
        $fp = $this->flashPrice($flash, $item['price']);
        $rem = $this->left($flash, $consumed);
        $flashQty = $rem === null ? $qty : min($qty, $rem);
        $consumed[$flash->id] = ($consumed[$flash->id] ?? 0) + $flashQty;
        $out = [['unit_price' => $fp, 'qty' => $flashQty, 'flash' => $flash]];
        if ($qty > $flashQty) {
            $out[] = ['unit_price' => $item['price'], 'qty' => $qty - $flashQty, 'flash' => null];
        }

        return $out;
    }
}
