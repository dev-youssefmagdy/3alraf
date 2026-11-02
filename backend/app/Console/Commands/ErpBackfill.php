<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Erp\Backfill\BackfillService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class ErpBackfill extends Command
{
    protected $signature = 'erp:backfill {--tenant= : Tenant slug or "all"} {--from= : Opening date YYYY-MM-DD (documents confirmed before it become opening balances)}
        {--dry-run : Count and total only, write nothing} {--only= : Comma list of orders,returns,purchases,stock,expenses,payments,opening}';

    protected $description = 'Idempotent historical backfill of the ledger (opening balances + business documents at their own dates)';

    public function handle(BackfillService $svc): int
    {
        $slug = $this->option('tenant');
        $from = (string) $this->option('from');
        if (! $slug || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || ! Carbon::hasFormat($from, 'Y-m-d')) {
            $this->error('Usage: erp:backfill --tenant=<slug|all> --from=YYYY-MM-DD [--dry-run] [--only=...]');

            return self::FAILURE;
        }
        $only = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('only')))));
        if ($bad = array_diff($only, BackfillService::TYPES)) {
            $this->error('Unknown --only type(s): '.implode(', ', $bad).'. Allowed: '.implode(', ', BackfillService::TYPES));

            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');
        $tenants = Tenant::query()->when($slug !== 'all', fn ($q) => $q->where('slug', $slug))->get();
        if ($tenants->isEmpty()) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }
        $failed = false;
        foreach ($tenants as $t) {
            $this->info("== {$t->slug}".($dry ? ' (dry-run)' : '')." from $from");
            $bar = null;
            $progress = function (string $ev, string $type, int $n) use (&$bar) {
                if ($ev === 'start') {
                    $bar = 0;
                } elseif ($ev === 'tick' && ++$bar % 200 === 0) {
                    $this->output->write(".");
                } elseif ($ev === 'end') {
                    $this->line(" $type: $bar scanned");
                }
            };
            try {
                $res = $svc->run($t, $from, $dry, $only, $progress, 'cli:'.(get_current_user() ?: 'cli'));
            } catch (Throwable $e) {
                $this->error("{$t->slug}: ".$e->getMessage());
                $failed = true;

                continue;
            }
            if ($dry && ! empty($res['closed'])) {
                $this->warn('Closed periods (a real run would refuse): '.implode(', ', $res['closed']));
            }
            $rows = [];
            foreach ($res['counts'] as $type => $c) {
                $rows[] = [$type, $c['created'], $c['skipped'], $c['failed'], $c['amount']];
            }
            $this->table(['type', $dry ? 'would create' : 'created', 'skipped', 'failed', 'amount'], $rows);
            if ($res['opening']) {
                $this->table(['opening component', 'total (Dr +, Cr -)'], collect($res['opening'])->map(fn ($v, $k) => [$k, $v])->values()->all());
            }
            $this->line("status: {$res['status']}");
            if ($res['status'] === 'partial') {
                $failed = true;
                $this->warn('Some documents failed: see erp_audit_logs action backfill.failed, fix the cause and re-run (idempotent).');
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
