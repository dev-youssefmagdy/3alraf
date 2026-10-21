<?php

namespace App\Services;

use App\Repositories\Contracts\AffiliateTierRepositoryInterface;

class AffiliateTierService extends CrudService
{
    public function __construct(AffiliateTierRepositoryInterface $repo)
    {
        parent::__construct($repo);
    }
}
