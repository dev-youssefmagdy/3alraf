<?php

namespace App\Repositories\Eloquent;

use App\Models\PurchaseOrder;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;

class PurchaseOrderRepository extends BaseRepository implements PurchaseOrderRepositoryInterface
{
    protected string $model = PurchaseOrder::class;
}
