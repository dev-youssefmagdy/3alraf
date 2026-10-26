<?php

namespace App\Services\Ai\Tools;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\TenantProduct;

/** Shared product shaping for the product tools (tenant-scoped TenantProduct overlay over the central catalog). */
trait ProductSupport
{
    /** @return array<int,array{on_hand:int,reserved:int}> product_id => totals (all branches of the current tenant) */
    protected function stockByProduct(array $productIds): array
    {
        $rows = StockLevel::query()->whereIn('product_id', $productIds)->selectRaw('product_id, SUM(on_hand) as oh, SUM(reserved) as rs')->groupBy('product_id')->get();

        return $rows->mapWithKeys(fn ($r) => [$r->product_id => ['on_hand' => (int) $r->oh, 'reserved' => (int) $r->rs]])->all();
    }

    protected function productRow(TenantProduct $tp, array $stock, ?string $currency): array
    {
        /** @var Product $p */
        $p = $tp->product;
        $s = $stock[$tp->product_id] ?? ['on_hand' => 0, 'reserved' => 0];

        return [
            'id' => $tp->product_id, 'name' => $this->data($p->getTranslation('name')), 'sku' => $p->sku, 'type' => $p->type?->value,
            'price' => $tp->price, 'sale_price' => $tp->sale_price, 'currency' => $currency, 'active' => (bool) $tp->is_active && (bool) $p->is_active,
            'available' => $s['on_hand'] - $s['reserved'],
        ];
    }
}
