<?php

namespace App\Repositories\Eloquent;

use App\Models\Supplier;
use App\Repositories\Contracts\SupplierRepositoryInterface;

class SupplierRepository extends BaseRepository implements SupplierRepositoryInterface
{
    protected string $model = Supplier::class;
}
