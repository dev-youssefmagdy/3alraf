<?php

namespace App\Repositories\Eloquent;

use App\Models\ShippingCompany;
use App\Repositories\Contracts\ShippingCompanyRepositoryInterface;

class ShippingCompanyRepository extends BaseRepository implements ShippingCompanyRepositoryInterface
{
    protected string $model = ShippingCompany::class;
}
