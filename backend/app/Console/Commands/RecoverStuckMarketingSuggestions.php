<?php

namespace App\Console\Commands;

use App\Services\Marketing\Suggestions\SuggestionEngine;
use Illuminate\Console\Command;

/** Every 5 min: suggestions stuck in `applying` (request died mid-apply) become `failed` so they can be applied again. */
class RecoverStuckMarketingSuggestions extends Command
{
    protected $signature = 'marketing:recover-stuck-suggestions {--minutes=15 : Consider an apply stuck after this many minutes}';

    protected $description = 'Mark marketing suggestions stuck in applying as failed so they can be retried';

    public function handle(SuggestionEngine $engine): int
    {
        $this->info('Recovered: '.$engine->recoverStuck(max(1, (int) $this->option('minutes'))));

        return self::SUCCESS;
    }
}
