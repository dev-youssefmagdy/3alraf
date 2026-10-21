<?php

namespace App\Services;

use App\Repositories\Contracts\CityRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class CityService extends CrudService
{
    public function __construct(CityRepositoryInterface $repo)
    {
        parent::__construct($repo);
    }

    protected function guardDelete(Model $model): void
    {
        if ($model->branches()->exists() || $model->shippingRates()->exists()) {
            $this->block('ops.city_in_use');
        }
    }
}
