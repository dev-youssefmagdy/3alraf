<?php

namespace App\Services\Ai\Tools;

use App\Models\Product;
use App\Services\Ai\AiContext;

class SearchProductsTool extends AbstractTool
{
    use ProductSupport;

    public function name(): string { return 'search_products'; }

    public function description(): string { return 'Search the store catalog by name, SKU or barcode. Returns price, sale price, availability. Max 25 rows.'; }

    public function schema(): array
    {
        return $this->obj(['query' => ['type' => 'string'], 'only_active' => ['type' => 'boolean'], 'in_stock_only' => ['type' => 'boolean'], 'limit' => ['type' => 'integer']]);
    }

    protected function run(array $a, AiContext $ctx): array
    {
        $q = trim((string) ($a['query'] ?? ''));
        $query = Product::query()->whereNotNull('price');
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('sku', 'like', $like)->orWhere('barcode', 'like', $like));
        }
        if (! empty($a['only_active'])) {
            $query->where('is_active', true);
        }
        $rows = $query->orderBy('id')->limit($this->limit($a) * (empty($a['in_stock_only']) ? 1 : 4))->get();
        $stock = $this->stockByProduct($rows->pluck('id')->all());
        $out = $rows->map(fn ($p) => $this->productRow($p, $stock, $ctx->currency));
        if (! empty($a['in_stock_only'])) {
            $out = $out->filter(fn ($r) => $r['available'] > 0);
        }

        return ['count' => $out->count(), 'products' => $out->take($this->limit($a))->values()->all()];
    }
}
