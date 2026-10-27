<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Growth\GrowthAccess;
use App\Services\Growth\StatsAggregator;
use Illuminate\Console\Command;

class GrowthAggregate extends Command
{
    protected $signature = 'growth:aggregate {--days=3 : How many recent days to rebuild} {--tenant= : Tenant slug}';

    protected $description = 'Rebuild growth_daily_stats for growth-enabled tenants (idempotent)';

    public function handle(GrowthAccess $access, StatsAggregator $stats): int
    {
        $prev = app()->bound('currentTenant') ? app('currentTenant') : null;
        foreach (Tenant::query()->when($this->option('tenant'), fn ($q, $s) => $q->where('slug', $s))->get() as $t) {
            if (! $access->enabled($t)) {
                continue;
            }
            app()->instance('currentTenant', $t);
            $tz = $t->timezone ?: config('app.timezone');
            $to = now($tz)->startOfDay();
            $rows = $stats->rebuild($to->copy()->subDays(max(0, (int) $this->option('days') - 1)), $to);
            $this->info("{$t->slug}: $rows rows");
        }
        $prev ? app()->instance('currentTenant', $prev) : app()->forgetInstance('currentTenant');

        return self::SUCCESS;
    }
}
