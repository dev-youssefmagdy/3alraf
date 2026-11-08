<?php

namespace App\Console\Commands;

use App\Models\MarketingApiRequest;
use Illuminate\Console\Command;

/** Retention of the Marketing internal API request log (config marketing.request_log_days, default 30). */
class MarketingPruneRequests extends Command
{
    protected $signature = 'marketing:prune-requests {--dry-run : only count}';

    protected $description = 'Delete marketing_api_requests (Marketing API call log) older than config marketing.request_log_days';

    public function handle(): int
    {
        $q = MarketingApiRequest::where('created_at', '<', now()->subDays(max(1, (int) config('marketing.request_log_days', 30))));
        $n = $this->option('dry-run') ? $q->count() : $q->delete();
        $this->info(($this->option('dry-run') ? 'Would delete: ' : 'Deleted: ').$n);

        return self::SUCCESS;
    }
}
