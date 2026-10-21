<?php

namespace App\Repositories\Eloquent;

use App\Models\AffiliateTier;
use App\Repositories\Contracts\AffiliateTierRepositoryInterface;

class AffiliateTierRepository extends BaseRepository implements AffiliateTierRepositoryInterface
{
    protected string $model = AffiliateTier::class;
}
