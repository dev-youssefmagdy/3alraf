<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface PurchaseOrderRepositoryInterface extends RepositoryInterface
{
    /** Lines of RECEIVED purchase orders for one product (all its variants), newest first. */
    public function receivedItemsForProduct(int $productId): Collection;
}
