<?php

namespace App\Services\Ai\Tools;

use App\Models\Bundle;
use App\Services\Ai\AiContext;

class ListBundlesTool extends AbstractTool
{
    public function name(): string { return 'list_bundles'; }

    public function description(): string { return 'List bundles (fixed-price packs) with components and quantities. live_only=true shows only currently sellable ones.'; }

    public function schema(): array
    {
        return $this->obj(['live_only' => ['type' => 'boolean'], 'limit' => ['type' => 'integer']]);
    }

    protected function run(array $a, AiContext $ctx): array
    {
        $q = Bundle::query()->with('items')->orderBy('sort_order')->orderBy('id');
        if (! empty($a['live_only'])) {
            $q->live();
        }
        $bundles = $q->limit($this->limit($a))->get();
        $products = \App\Models\Product::query()->whereIn('id', $bundles->flatMap->items->pluck('product_id')->unique())->get()->keyBy('id');

        return ['count' => $bundles->count(), 'currency' => $ctx->currency, 'bundles' => $bundles->map(fn ($b) => [
            'id' => $b->id, 'name' => $this->data($b->getTranslation('name'), 120), 'price' => $b->price, 'live' => $b->isLive(), 'starts_at' => $b->starts_at?->toDateString(), 'ends_at' => $b->ends_at?->toDateString(),
            'items' => $b->items->map(fn ($i) => ['product_id' => $i->product_id, 'name' => $this->data($products->get($i->product_id)?->getTranslation('name'), 100), 'sku' => $products->get($i->product_id)?->sku, 'qty' => $i->quantity])->all(),
        ])->all()];
    }
}
