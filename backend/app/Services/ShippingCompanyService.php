<?php

namespace App\Services;

use App\Repositories\Contracts\ShippingCompanyRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ShippingCompanyService extends CrudService
{
    public function __construct(ShippingCompanyRepositoryInterface $repo)
    {
        parent::__construct($repo);
    }

    public function create(object $dto): Model
    {
        return DB::transaction(function () use ($dto) {
            $company = $this->repo->create($dto->toArray());
            $this->syncRates($company, $dto->rates);

            return $company;
        });
    }

    public function update(Model $model, object $dto): Model
    {
        return DB::transaction(function () use ($model, $dto) {
            $company = $this->repo->update($model, $dto->toArray());
            $this->syncRates($company, $dto->rates);

            return $company;
        });
    }

    /** Per-city price list: replace the set (unique company+city). */
    private function syncRates(Model $company, array $rates): void
    {
        $company->rates()->delete();
        foreach ($rates as $rate) {
            $company->rates()->create($rate);
        }
    }
}
