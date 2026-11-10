<?php

namespace App\Events\Erp;

use App\Models\AffiliateBonus;
use Illuminate\Foundation\Events\Dispatchable;

/** ERP hook: an affiliate sales bonus was awarded (Dr Affiliate expense / Cr Affiliate payable). Fired after commit. */
class AffiliateBonusAwarded
{
    use Dispatchable;

    public function __construct(public AffiliateBonus $bonus) {}
}
