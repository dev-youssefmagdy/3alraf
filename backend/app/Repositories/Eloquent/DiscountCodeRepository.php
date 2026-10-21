<?php

namespace App\Repositories\Eloquent;

use App\Models\DiscountCode;
use App\Repositories\Contracts\DiscountCodeRepositoryInterface;

class DiscountCodeRepository extends BaseRepository implements DiscountCodeRepositoryInterface
{
    protected string $model = DiscountCode::class;
}
