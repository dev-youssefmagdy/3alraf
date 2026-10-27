<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Growth\GrowthRetentionService;
use Illuminate\Console\Command;

class GrowthPrune extends Command
{
    protected $signature = 'growth:prune {--tenant= : Tenant slug} {--dry-run : only count} {--erase-thread= : erase interactions of this thread/external id (needs --tenant)} {--erase-contact= : erase small-batch signups of this contact (needs --tenant)}';

    protected $description = 'Growth retention (interactions, events, small-batch contacts, ad data, action logs) and data-subject erasure';

    public function handle(GrowthRetentionService $svc): int
    {
        $tenant = $this->option('tenant') ? Tenant::where('slug', $this->option('tenant'))->first() : null;
        if ($this->option('tenant') && ! $tenant) {
            $this->error('Unknown tenant');

            return self::FAILURE;
        }
        if ($this->option('erase-thread') || $this->option('erase-contact')) {
            if (! $tenant) {
                $this->error('--tenant is required for erasure');

                return self::FAILURE;
            }
            $this->line(json_encode($svc->erase($tenant, $this->option('erase-thread'), $this->option('erase-contact'))));

            return self::SUCCESS;
        }
        $this->line(json_encode($svc->prune($tenant, (bool) $this->option('dry-run'))));

        return self::SUCCESS;
    }
}
