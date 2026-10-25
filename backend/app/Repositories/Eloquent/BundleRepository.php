<?php

namespace App\Repositories\Eloquent;

use App\Models\Bundle;
use App\Repositories\Contracts\BundleRepositoryInterface;

class BundleRepository extends BaseRepository implements BundleRepositoryInterface
{
    protected string $model = Bundle::class;
}
