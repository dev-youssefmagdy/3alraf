<?php

namespace App\Repositories\Eloquent;

use App\Models\Banner;
use App\Repositories\Contracts\BannerRepositoryInterface;

class BannerRepository extends BaseRepository implements BannerRepositoryInterface
{
    protected string $model = Banner::class;
}
