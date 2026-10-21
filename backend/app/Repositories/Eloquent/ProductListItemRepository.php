<?php

namespace App\Repositories\Eloquent;

use App\Models\ProductListItem;
use App\Repositories\Contracts\ProductListItemRepositoryInterface;

class ProductListItemRepository extends BaseRepository implements ProductListItemRepositoryInterface
{
    protected string $model = ProductListItem::class;
}
