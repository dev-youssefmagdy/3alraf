<?php

namespace App\Repositories\Eloquent;

use App\Models\Cart;
use App\Repositories\Contracts\CartRepositoryInterface;

class CartRepository extends BaseRepository implements CartRepositoryInterface
{
    protected string $model = Cart::class;
}
