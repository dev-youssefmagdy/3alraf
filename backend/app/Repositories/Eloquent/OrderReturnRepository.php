<?php

namespace App\Repositories\Eloquent;

use App\Models\OrderReturn;
use App\Repositories\Contracts\OrderReturnRepositoryInterface;

class OrderReturnRepository extends BaseRepository implements OrderReturnRepositoryInterface
{
    protected string $model = OrderReturn::class;
}
