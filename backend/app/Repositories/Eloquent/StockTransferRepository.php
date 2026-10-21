<?php

namespace App\Repositories\Eloquent;

use App\Models\StockTransfer;
use App\Repositories\Contracts\StockTransferRepositoryInterface;

class StockTransferRepository extends BaseRepository implements StockTransferRepositoryInterface
{
    protected string $model = StockTransfer::class;
}
