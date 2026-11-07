<?php

namespace App\Console\Commands;

use App\Services\Growth\GrowthFeatures;
use App\Services\Growth\StatsAggregator;
use Illuminate\Console\Command;

class GrowthAggregate extends Command
{
    protected $signature = 'growth:aggregate {--days=3 : How many recent days to rebuild}';

    protected $description = 'Rebuild growth_daily_stats when the integration API or the brief is on (idempotent)';

    public function handle(GrowthFeatures $features, StatsAggregator $stats): int
    {
        if (! $features->anyActive('integration_api', 'brief')) {
            $this->info('skipped: integration_api and brief are off');

            return self::SUCCESS;
        }
        $to = now(setting('timezone') ?: config('app.timezone'))->startOfDay();
        $rows = $stats->rebuild($to->copy()->subDays(max(0, (int) $this->option('days') - 1)), $to);
        $this->info("$rows rows");

        return self::SUCCESS;
    }
}
