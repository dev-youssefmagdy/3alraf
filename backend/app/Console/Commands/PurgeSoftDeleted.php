<?php

namespace App\Console\Commands;

use App\Services\Retention\SoftDeletePurger;
use Illuminate\Console\Command;

class PurgeSoftDeleted extends Command
{
    protected $signature = 'retention:purge {--dry-run : only report what would happen} {--days= : retention window in days (default config retention.soft_delete_days)} {--model=* : limit to model keys (order, customer, product, ...)}';

    protected $description = 'Purge (or anonymise / retain) soft-deleted records older than the retention window, without ever touching rows that history and reports still need.';

    public function handle(SoftDeletePurger $purger): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : (int) config('retention.soft_delete_days');
        $only = $this->option('model') ?: null;
        if ($only && ($bad = array_diff($only, array_keys(config('retention.models'))))) {
            $this->error('Unknown model key(s): '.implode(', ', $bad).'. Tenants are never purged automatically. Valid: '.implode(', ', array_keys(config('retention.models'))));

            return self::INVALID;
        }
        $dry = (bool) $this->option('dry-run');

        $summary = $purger->run($days, $dry, $only);

        $rows = [];
        $total = ['purged' => 0, 'anonymized' => 0, 'retained' => 0];
        foreach ($summary as $key => $c) {
            $rows[] = [$key, $c['purged'], $c['anonymized'], $c['retained']];
            foreach ($c as $k => $n) {
                $total[$k] += $n;
            }
        }
        $this->table(['model', 'purged', 'anonymized', 'retained'], $rows);
        $this->info(($dry ? '[dry-run] ' : '')."older than {$days} days: {$total['purged']} purged, {$total['anonymized']} anonymized, {$total['retained']} retained.");

        if (! $dry) {
            activity('retention')->withProperties(['days' => $days, 'summary' => $summary, 'total' => $total])->log('purge');
        }

        return self::SUCCESS;
    }
}
