<?php

namespace App\Services;

use App\Repositories\Contracts\BlogPostRepositoryInterface;

class BlogPostService extends CrudService
{
    public function __construct(BlogPostRepositoryInterface $repo)
    {
        parent::__construct($repo);
    }
}
