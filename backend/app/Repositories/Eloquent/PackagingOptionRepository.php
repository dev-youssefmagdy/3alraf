<?php

namespace App\Repositories\Eloquent;

use App\Models\PackagingOption;
use App\Repositories\Contracts\PackagingOptionRepositoryInterface;

class PackagingOptionRepository extends BaseRepository implements PackagingOptionRepositoryInterface
{
    protected string $model = PackagingOption::class;
}
