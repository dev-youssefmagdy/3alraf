<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Erp\Reconciliation\ReconcileRunner;
use Illuminate\Console\Command;

class ErpReconcile extends Command
{
    protected $signature = 'erp:reconcile {--tenant= : Tenant slug (default all)} {--json : Machine-readable output}';

    protected $description = 'Run the ledger-vs-business-records reconciliation checks per tenant; exits non-zero on any mismatch';

    public function handle(ReconcileRunner $runner): int
    {
        $tenants = Tenant::query()->when($this->option('tenant'), fn ($q, $s) => $q->where('slug', $s))->get();
        if ($tenants->isEmpty()) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }
        $all = [];
        $bad = 0;
        foreach ($tenants as $t) {
            $r = $runner->run($t);
            $all[$t->slug] = $r;
            $bad += $r['mismatches'];
        }
        if ($this->option('json')) {
            $this->line(json_encode(['ok' => $bad === 0, 'mismatches' => $bad, 'tenants' => $all], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            foreach ($all as $slug => $r) {
                $this->info("== $slug (from {$r['from']} to {$r['to']}) mismatches: {$r['mismatches']}");
                $this->table(['check', 'expected', 'actual', 'difference', 'status'], array_map(fn ($c) => [$c['key'], $c['expected'], $c['actual'], $c['difference'], $c['ok'] ? 'ok' : 'MISMATCH'], $r['checks']));
            }
        }

        return $bad === 0 ? self::SUCCESS : self::FAILURE;
    }
}
