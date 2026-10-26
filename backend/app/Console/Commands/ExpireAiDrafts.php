<?php

namespace App\Console\Commands;

use App\Services\Ai\GeneratorService;
use Illuminate\Console\Command;

class ExpireAiDrafts extends Command
{
    protected $signature = 'ai:expire-drafts';

    protected $description = 'Reject stale AI generator drafts (older than '.GeneratorService::TTL_DAYS.' days)';

    public function handle(GeneratorService $svc): int
    {
        $this->info('Expired: '.$svc->expireStale());

        return self::SUCCESS;
    }
}
