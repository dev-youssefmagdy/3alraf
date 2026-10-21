<?php

namespace App\Repositories\Eloquent;

use App\Models\CustomPage;
use App\Repositories\Contracts\CustomPageRepositoryInterface;

class CustomPageRepository extends BaseRepository implements CustomPageRepositoryInterface
{
    protected string $model = CustomPage::class;
}
