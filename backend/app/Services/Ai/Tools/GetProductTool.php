<?php

namespace App\Services\Ai\Tools;

use App\Models\BundleItem;
use App\Models\StockLevel;
use App\Models\TenantProduct;
use App\Services\Ai\AiContext;

class GetProductTool extends AbstractTool
{
    use ProductSupport;

    public function name(): string { return 'get_product'; }

    public function description(): string { return 'Full detail of one product (id or SKU): description, variants with SKU/price/cost, stock per branch, categories, tags, bundles containing it.'; }

    public function schema(): array
    {
        return $this->obj(['product_id' => ['type' => 'integer'], 'sku' => ['type' => 'string']]);
    }

    protected function run(array $a, AiContext $ctx): array
    {
        $q = TenantProduct::query()->with(['product.categories', 'product.tags', 'product.variants.values', 'variants']);
        if (! empty($a['product_id'])) {
            $q->where('product_id', (int) $a['product_id']);
        } elseif (! empty($a['sku'])) {
            $q->whereHas('product', fn ($p) => $p->where('sku', (string) $a['sku'])->orWhereHas('variants', fn ($v) => $v->where('sku', (string) $a['sku'])));
        } else {
            return ['error' => 'product_id or sku required'];
        }
        $tp = $q->first();
        if (! $tp) {
            return ['error' => 'not_found'];
        }
        $p = $tp->product;
        $overlay = $tp->variants->keyBy('product_variant_id');
        $levels = StockLevel::query()->where('product_id', $p->id)->with('branch')->get();
        $variants = $p->variants->map(function ($v) use ($overlay, $tp, $levels) {
            $o = $overlay->get($v->id);
            $lv = $levels->where('product_variant_id', $v->id);

            return [
                'id' => $v->id, 'label' => $this->data($v->label(), 120), 'sku' => $v->sku, 'price' => $o?->price ?? $tp->price, 'sale_price' => $o?->sale_price ?? $tp->sale_price,
                'cost' => $o?->cost ?? $tp->cost, 'active' => (bool) $v->is_active && (bool) ($o?->is_active ?? true), 'available' => (int) ($lv->sum('on_hand') - $lv->sum('reserved')),
            ];
        })->values()->all();
        $perBranch = $levels->groupBy('branch_id')->map(fn ($g) => ['branch' => $this->data($g->first()->branch?->name, 80), 'on_hand' => (int) $g->sum('on_hand'), 'reserved' => (int) $g->sum('reserved'), 'available' => (int) ($g->sum('on_hand') - $g->sum('reserved'))])->values()->all();
        $bundles = BundleItem::query()->where('product_id', $p->id)->with('bundle')->get()->map(fn ($i) => $i->bundle)->filter()->unique('id')
            ->map(fn ($b) => ['id' => $b->id, 'name' => $this->data($b->getTranslation('name'), 100), 'price' => $b->price, 'live' => $b->isLive()])->values()->all();

        return $this->productRow($tp, $this->stockByProduct([$p->id]), $ctx->currency) + [
            'cost' => $tp->cost, 'description' => $this->data($p->getTranslation('description'), 800), 'barcode' => $p->barcode,
            'categories' => $p->categories->map(fn ($c) => $this->data($c->getTranslation('name'), 80))->values()->all(),
            'tags' => $p->tags->map(fn ($t) => $this->data($t->getTranslation('name'), 60))->values()->all(),
            'variants' => array_slice($variants, 0, 40), 'stock_by_branch' => $perBranch, 'bundles' => array_slice($bundles, 0, 10),
        ];
    }
}
