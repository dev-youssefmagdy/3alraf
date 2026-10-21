<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Collection;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    protected string $model = Product::class;

    public function syncCategories(Product $product, array $categoryIds): void
    {
        $product->categories()->sync($categoryIds);
    }

    public function variantsWithTrashed(Product $product): Collection
    {
        return $product->variants()->withTrashed()->get()->keyBy('id');
    }
}
