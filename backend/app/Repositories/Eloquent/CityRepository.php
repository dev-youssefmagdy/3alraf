<?php

namespace App\Repositories\Eloquent;

use App\Models\City;
use App\Repositories\Contracts\CityRepositoryInterface;

class CityRepository extends BaseRepository implements CityRepositoryInterface
{
    protected string $model = City::class;
}
