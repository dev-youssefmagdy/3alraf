<?php

namespace App\Repositories\Eloquent;

use App\Models\AffiliatePayout;
use App\Repositories\Contracts\AffiliatePayoutRepositoryInterface;

class AffiliatePayoutRepository extends BaseRepository implements AffiliatePayoutRepositoryInterface
{
    protected string $model = AffiliatePayout::class;
}
