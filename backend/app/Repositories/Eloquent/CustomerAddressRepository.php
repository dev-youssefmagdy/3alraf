<?php

namespace App\Repositories\Eloquent;

use App\Models\CustomerAddress;
use App\Repositories\Contracts\CustomerAddressRepositoryInterface;

class CustomerAddressRepository extends BaseRepository implements CustomerAddressRepositoryInterface
{
    protected string $model = CustomerAddress::class;
}
