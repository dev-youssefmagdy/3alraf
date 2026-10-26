<?php

namespace App\Repositories\Eloquent;

use App\Models\BlogPost;
use App\Repositories\Contracts\BlogPostRepositoryInterface;

class BlogPostRepository extends BaseRepository implements BlogPostRepositoryInterface
{
    protected string $model = BlogPost::class;
}
