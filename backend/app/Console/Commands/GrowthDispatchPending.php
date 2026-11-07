<?php

namespace App\Console\Commands;

use App\Jobs\DispatchGrowthEventJob;
use App\Repositories\Contracts\GrowthEventRepositoryInterface;
use App\Services\Growth\GrowthFeatures;
use Illuminate\Console\Command;

class GrowthDispatchPending extends Command
{
    protected $signature = 'growth:dispatch-pending';

    protected $description = 'Queue due outbox events (retries with backoff, crashed leases)';

    public function handle(GrowthFeatures $features, GrowthEventRepositoryInterface $repo): int
    {
        $n = 0;
        if (! $features->active('events_outbox')) {
            $this->info('Queued: 0 (events_outbox is off)');

            return self::SUCCESS;
        }
        $repo->dueIds()
            ->each(function ($id) use (&$n) {
                DispatchGrowthEventJob::dispatch($id);
                $n++;
            });
        $this->info("Queued: $n");

        return self::SUCCESS;
    }
}
