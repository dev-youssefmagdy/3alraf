<?php

namespace App\Services;

use App\Repositories\Contracts\StaticPageRepositoryInterface;

class StaticPageService extends CrudService
{
    public function __construct(StaticPageRepositoryInterface $repo)
    {
        parent::__construct($repo);
    }
}
