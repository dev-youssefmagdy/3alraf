<?php

namespace App\Events\Erp;

use Illuminate\Foundation\Events\Dispatchable;

/** ERP hook: an ambassador's valid rewards were paid with an affiliate payout run (Dr Marketing / Cr the paying account). */
class AmbassadorRewardsPaid
{
    use Dispatchable;

    public function __construct(public int $affiliateId, public string $amount, public string $ref, public string $paidFrom = 'cash') {}
}
