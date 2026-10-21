<?php

namespace App\Repositories\Eloquent;

use App\Models\Brand;
use App\Repositories\Contracts\BrandRepositoryInterface;

class BrandRepository extends BaseRepository implements BrandRepositoryInterface
{
    protected string $model = Brand::class;
}
