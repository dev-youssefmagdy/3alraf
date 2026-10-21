<?php

namespace App\Repositories\Eloquent;

use App\Models\StockMovement;
use App\Repositories\Contracts\StockMovementRepositoryInterface;

class StockMovementRepository extends BaseRepository implements StockMovementRepositoryInterface
{
    protected string $model = StockMovement::class;
}
