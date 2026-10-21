<?php

namespace App\Repositories\Eloquent;

use App\Models\WishlistItem;
use App\Repositories\Contracts\WishlistItemRepositoryInterface;

class WishlistItemRepository extends BaseRepository implements WishlistItemRepositoryInterface
{
    protected string $model = WishlistItem::class;
}
