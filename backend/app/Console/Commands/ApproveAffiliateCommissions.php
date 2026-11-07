<?php

namespace App\Console\Commands;

use App\Services\Affiliates\AffiliateCommissionService;
use Illuminate\Console\Command;

/** Pending affiliate commissions -> approved once the order is delivered and the return window has passed. */
class ApproveAffiliateCommissions extends Command
{
    protected $signature = 'affiliates:approve-commissions';

    protected $description = 'Approve affiliate commissions whose return window has elapsed';

    public function handle(AffiliateCommissionService $service): int
    {
        $this->line($service->approveDue().' approved');

        return self::SUCCESS;
    }
}
