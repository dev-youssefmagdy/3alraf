<?php

namespace App\Repositories\Eloquent;

use App\Models\Tag;
use App\Repositories\Contracts\TagRepositoryInterface;

class TagRepository extends BaseRepository implements TagRepositoryInterface
{
    protected string $model = Tag::class;
}
