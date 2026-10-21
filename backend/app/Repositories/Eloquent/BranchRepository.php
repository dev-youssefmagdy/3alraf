<?php

namespace App\Repositories\Eloquent;

use App\Models\Branch;
use App\Repositories\Contracts\BranchRepositoryInterface;

class BranchRepository extends BaseRepository implements BranchRepositoryInterface
{
    protected string $model = Branch::class;
}
