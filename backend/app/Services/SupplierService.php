<?php

namespace App\Services;

use App\Repositories\Contracts\SupplierRepositoryInterface;

class SupplierService extends CrudService
{
    public function __construct(SupplierRepositoryInterface $repo)
    {
        parent::__construct($repo);
    }
}
