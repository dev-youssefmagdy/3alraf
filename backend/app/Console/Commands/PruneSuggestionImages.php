<?php

namespace App\Console\Commands;

use App\Services\Marketing\Suggestions\SuggestionImages;
use Illuminate\Console\Command;

/** Daily: images downloaded for new_product suggestions (private disk) that no open suggestion needs any more, older than --days, are deleted. */
class PruneSuggestionImages extends Command
{
    protected $signature = 'marketing:prune-suggestion-images {--days=7 : keep unreferenced files this many days}';

    protected $description = 'Delete staged new-product suggestion images no open suggestion references';

    public function handle(SuggestionImages $images): int
    {
        $this->info('Removed: '.$images->prune(max(1, (int) $this->option('days'))));

        return self::SUCCESS;
    }
}
