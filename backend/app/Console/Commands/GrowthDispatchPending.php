<?php

namespace App\Console\Commands;

use App\Jobs\DispatchGrowthEventJob;
use App\Models\GrowthEvent;
use Illuminate\Console\Command;

class GrowthDispatchPending extends Command
{
    protected $signature = 'growth:dispatch-pending';

    protected $description = 'Queue due outbox events (retries with backoff, crashed leases)';

    public function handle(): int
    {
        $n = 0;
        GrowthEvent::withoutGlobalScopes()->where('status', 'pending')->where('next_attempt_at', '<=', now())->orderBy('id')->limit(500)->pluck('id')
            ->each(function ($id) use (&$n) {
                DispatchGrowthEventJob::dispatch($id);
                $n++;
            });
        $this->info("Queued: $n");

        return self::SUCCESS;
    }
}
