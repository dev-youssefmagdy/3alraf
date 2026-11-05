<?php

namespace App\Events\Erp;

use App\Models\AffiliatePayout;
use Illuminate\Foundation\Events\Dispatchable;

/** ERP hook: an affiliate payout was marked paid (Dr Affiliate payable / Cr the account it was paid from). Fired after commit. */
class AffiliatePayoutPaid
{
    use Dispatchable;

    public function __construct(public AffiliatePayout $payout) {}
}
