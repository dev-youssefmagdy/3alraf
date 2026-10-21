<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Support\Collection;

interface ProductRepositoryInterface extends RepositoryInterface
{
    public function syncCategories(Product $product, array $categoryIds): void;

    public function syncTags(Product $product, array $tagIds): void;

    /** All variants of the product including soft-deleted, keyed by id. */
    public function variantsWithTrashed(Product $product): Collection;
}
