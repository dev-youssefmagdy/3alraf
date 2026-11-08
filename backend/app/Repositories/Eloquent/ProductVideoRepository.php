<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Models\ProductVideo;
use App\Repositories\Contracts\ProductVideoRepositoryInterface;

class ProductVideoRepository extends BaseRepository implements ProductVideoRepositoryInterface
{
    protected string $model = ProductVideo::class;

    public function lastMediaOrder(Product $product): int
    {
        return (int) max($product->images()->reorder()->max('sort_order'), $product->videos()->reorder()->max('sort_order'));
    }

    public function applyOrder(Product $product, array $items): void
    {
        foreach ($items as $position => $item) {
            $relation = $item['type'] === 'video' ? $product->videos() : $product->images();
            $relation->reorder()->whereKey($item['id'])->update(['sort_order' => $position + 1]);
        }
    }
}
