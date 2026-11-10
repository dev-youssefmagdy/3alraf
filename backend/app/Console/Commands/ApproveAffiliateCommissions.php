<?php

namespace App\Console\Commands;

use App\Services\Affiliates\AffiliateBonusService;
use App\Services\Affiliates\AffiliateCommissionService;
use Illuminate\Console\Command;

/** Pending affiliate commissions -> approved once the order is delivered and the return window has passed; then award due sales bonuses. */
class ApproveAffiliateCommissions extends Command
{
    protected $signature = 'affiliates:approve-commissions';

    protected $description = 'Approve affiliate commissions whose return window has elapsed and award due sales bonuses';

    public function handle(AffiliateCommissionService $service, AffiliateBonusService $bonuses): int
    {
        $this->line($service->approveDue().' approved');
        $this->line($bonuses->awardDue().' bonuses awarded'); // same moment: sales past the return window (docs/CALCULATIONS.md §Affiliate sales bonus)

        return self::SUCCESS;
    }
}
