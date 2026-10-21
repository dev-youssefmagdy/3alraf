<?php

namespace App\Repositories\Eloquent;

use App\Models\StaticPage;
use App\Repositories\Contracts\StaticPageRepositoryInterface;

class StaticPageRepository extends BaseRepository implements StaticPageRepositoryInterface
{
    protected string $model = StaticPage::class;
}
