<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Affiliates\AffiliateCommissionService;
use Illuminate\Console\Command;

/** Pending affiliate commissions -> approved once the order is delivered and the tenant's return window has passed. */
class ApproveAffiliateCommissions extends Command
{
    protected $signature = 'affiliates:approve-commissions';

    protected $description = 'Approve affiliate commissions whose return window has elapsed';

    public function handle(AffiliateCommissionService $service): int
    {
        foreach (Tenant::where('is_active', true)->get() as $tenant) {
            app()->instance('currentTenant', $tenant);
            $n = $service->approveDue();
            $this->line("{$tenant->slug}: $n approved");
        }
        app()->forgetInstance('currentTenant');

        return self::SUCCESS;
    }
}
