<?php

namespace App\Repositories\Eloquent;

use App\Models\AffiliateCommission;
use App\Repositories\Contracts\AffiliateCommissionRepositoryInterface;

class AffiliateCommissionRepository extends BaseRepository implements AffiliateCommissionRepositoryInterface
{
    protected string $model = AffiliateCommission::class;
}
