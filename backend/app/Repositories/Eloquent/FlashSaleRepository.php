<?php

namespace App\Repositories\Eloquent;

use App\Models\FlashSale;
use App\Repositories\Contracts\FlashSaleRepositoryInterface;

class FlashSaleRepository extends BaseRepository implements FlashSaleRepositoryInterface
{
    protected string $model = FlashSale::class;
}
