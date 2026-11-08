<?php

namespace App\Repositories\Contracts;

use App\Models\Product;

interface ProductVideoRepositoryInterface extends RepositoryInterface
{
    /** Highest sort_order used by the product's images and videos together (0 when it has none). */
    public function lastMediaOrder(Product $product): int;

    /** Write the gallery positions. @param  array<int,array{type:string,id:int}>  $items  in display order */
    public function applyOrder(Product $product, array $items): void;
}
