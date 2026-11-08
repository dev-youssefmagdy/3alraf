<?php

namespace App\Console\Commands;

use App\Services\Marketing\Suggestions\SuggestionEngine;
use Illuminate\Console\Command;

/** Daily: open marketing suggestions past their expires_at become `expired` (approvals invalidated, activity logged as system). */
class ExpireMarketingSuggestions extends Command
{
    protected $signature = 'marketing:expire-suggestions';

    protected $description = 'Mark open marketing suggestions past their expiry date as expired';

    public function handle(SuggestionEngine $engine): int
    {
        $this->info('Expired: '.$engine->expireDue());

        return self::SUCCESS;
    }
}
