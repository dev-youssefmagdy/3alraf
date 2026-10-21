<?php

namespace App\Repositories\Eloquent;

use App\Models\Affiliate;
use App\Repositories\Contracts\AffiliateRepositoryInterface;

class AffiliateRepository extends BaseRepository implements AffiliateRepositoryInterface
{
    protected string $model = Affiliate::class;
}
