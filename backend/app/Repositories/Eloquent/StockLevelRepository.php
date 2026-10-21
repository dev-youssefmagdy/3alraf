<?php

namespace App\Repositories\Eloquent;

use App\Models\StockLevel;
use App\Repositories\Contracts\StockLevelRepositoryInterface;

class StockLevelRepository extends BaseRepository implements StockLevelRepositoryInterface
{
    protected string $model = StockLevel::class;
}
