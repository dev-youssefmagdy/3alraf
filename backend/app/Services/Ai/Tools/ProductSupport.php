<?php

namespace App\Services\Ai\Tools;

use App\Models\Product;
use App\Models\StockLevel;

/** Shared product shaping for the product tools (listed products: those with a store price). */
trait ProductSupport
{
    /** @return array<int,array{on_hand:int,reserved:int}> product_id => totals (all branches) */
    protected function stockByProduct(array $productIds): array
    {
        $rows = StockLevel::query()->whereIn('product_id', $productIds)->selectRaw('product_id, SUM(on_hand) as oh, SUM(reserved) as rs')->groupBy('product_id')->get();

        return $rows->mapWithKeys(fn ($r) => [$r->product_id => ['on_hand' => (int) $r->oh, 'reserved' => (int) $r->rs]])->all();
    }

    protected function productRow(Product $p, array $stock, ?string $currency): array
    {
        $s = $stock[$p->id] ?? ['on_hand' => 0, 'reserved' => 0];

        return [
            'id' => $p->id, 'name' => $this->data($p->getTranslation('name')), 'sku' => $p->sku, 'type' => $p->type?->value,
            'price' => $p->price, 'sale_price' => $p->sale_price, 'currency' => $currency, 'active' => (bool) $p->is_active,
            'available' => $s['on_hand'] - $s['reserved'],
        ];
    }
}
