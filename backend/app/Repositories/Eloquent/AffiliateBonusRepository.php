<?php

namespace App\Repositories\Eloquent;

use App\Models\AffiliateBonus;
use App\Repositories\Contracts\AffiliateBonusRepositoryInterface;

class AffiliateBonusRepository extends BaseRepository implements AffiliateBonusRepositoryInterface
{
    protected string $model = AffiliateBonus::class;
}
