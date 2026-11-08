<?php

namespace App\Console\Commands;

use App\Models\IntegrationRequest;
use Illuminate\Console\Command;

/** Retention of the integration API request log (config integration.request_log_days, default 30). */
class IntegrationPrune extends Command
{
    protected $signature = 'integration:prune {--dry-run : only count}';

    protected $description = 'Delete integration_requests (n8n API call log) older than config integration.request_log_days';

    public function handle(): int
    {
        $q = IntegrationRequest::where('created_at', '<', now()->subDays(max(1, (int) config('integration.request_log_days', 30))));
        $n = $this->option('dry-run') ? $q->count() : $q->delete();
        $this->info(($this->option('dry-run') ? 'Would delete: ' : 'Deleted: ').$n);

        return self::SUCCESS;
    }
}
