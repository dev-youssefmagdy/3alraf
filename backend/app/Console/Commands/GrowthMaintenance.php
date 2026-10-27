<?php

namespace App\Console\Commands;

use App\Services\Growth\ApprovalService;
use App\Services\Growth\GrowthEventService;
use App\Services\Growth\IdempotencyService;
use Illuminate\Console\Command;

class GrowthMaintenance extends Command
{
    protected $signature = 'growth:maintain';

    protected $description = 'Expire stale approvals; prune old delivered events and idempotency records';

    public function handle(ApprovalService $approvals, GrowthEventService $events, IdempotencyService $idem): int
    {
        $this->info('Approvals expired: '.$approvals->expireDue().' | events pruned: '.$events->prune().' | idempotency pruned: '.$idem->prune());

        return self::SUCCESS;
    }
}
