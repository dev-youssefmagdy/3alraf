<?php

namespace App\Console\Commands;

use App\Services\Growth\GrowthRetentionService;
use Illuminate\Console\Command;

class GrowthPrune extends Command
{
    protected $signature = 'growth:prune {--dry-run : only count} {--erase-thread= : erase interactions of this thread/external id} {--erase-contact= : erase small-batch signups of this contact}';

    protected $description = 'Growth retention (interactions, events, small-batch contacts, ad data, action logs) and data-subject erasure';

    public function handle(GrowthRetentionService $svc): int
    {
        if ($this->option('erase-thread') || $this->option('erase-contact')) {
            $this->line(json_encode($svc->erase($this->option('erase-thread'), $this->option('erase-contact'))));

            return self::SUCCESS;
        }
        $this->line(json_encode($svc->prune((bool) $this->option('dry-run'))));

        return self::SUCCESS;
    }
}
