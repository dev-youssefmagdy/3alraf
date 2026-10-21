<?php

namespace App\Services\Stock;

use App\Models\Product;
use App\Models\TenantProduct;

/** Stockable items for the tenant: enabled simple products + variants of enabled variable products. Key = "productId-variantId". */
class StockItemCatalog
{
    /** @return array<int,array{key:string,label:string,sku:?string,cost:string}> */
    public function options(): array
    {
        $out = [];
        $tps = TenantProduct::with(['product.variants.values'])->whereHas('product')->get();
        foreach ($tps as $tp) {
            $p = $tp->product;
            if ($p->isVariable()) {
                foreach ($p->variants->where('is_active', true) as $v) {
                    $out[] = ['key' => $p->id.'-'.$v->id, 'label' => $p->name.' - '.$v->label(), 'sku' => $v->sku, 'cost' => (string) $tp->cost];
                }
            } else {
                $out[] = ['key' => $p->id.'-0', 'label' => $p->name, 'sku' => $p->sku, 'cost' => (string) $tp->cost];
            }
        }

        return $out;
    }

    /** Valid key for current tenant? */
    public function exists(string $key): bool
    {
        [$pid, $vid] = $this->split($key);
        if (! TenantProduct::where('product_id', $pid)->exists()) {
            return false;
        }
        $p = Product::withTrashed()->with('variants')->find($pid);
        if (! $p) {
            return false;
        }

        return $p->isVariable() ? ($vid > 0 && $p->variants->contains('id', $vid)) : $vid === 0;
    }

    /** @return array{0:int,1:int} */
    public function split(string $key): array
    {
        $parts = explode('-', $key) + [1 => 0];

        return [(int) $parts[0], (int) $parts[1]];
    }

    /**
     * Request rows [{item,quantity,unit_cost?}] -> [{product_id,variant_id,quantity,unit_cost}].
     *
     * @return array<int,array{product_id:int,variant_id:int,quantity:int,unit_cost:?string}>
     */
    public function normalize(array $rows): array
    {
        return collect($rows)->map(function ($r) {
            [$pid, $vid] = $this->split($r['item']);
            $cost = $r['unit_cost'] ?? null;

            return ['product_id' => $pid, 'variant_id' => $vid, 'quantity' => (int) $r['quantity'], 'unit_cost' => ($cost === null || $cost === '') ? null : (string) $cost];
        })->values()->all();
    }
}
