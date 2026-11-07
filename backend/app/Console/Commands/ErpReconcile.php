<?php

namespace App\Console\Commands;

use App\Services\Erp\Reconciliation\ReconcileRunner;
use Illuminate\Console\Command;

class ErpReconcile extends Command
{
    protected $signature = 'erp:reconcile {--json : Machine-readable output}';

    protected $description = 'Run the ledger-vs-business-records reconciliation checks; exits non-zero on any mismatch';

    public function handle(ReconcileRunner $runner): int
    {
        $r = $runner->run();
        if ($this->option('json')) {
            $this->line(json_encode(['ok' => $r['mismatches'] === 0] + $r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->info("from {$r['from']} to {$r['to']} mismatches: {$r['mismatches']}");
            $this->table(['check', 'expected', 'actual', 'difference', 'status'], array_map(fn ($c) => [$c['key'], $c['expected'], $c['actual'], $c['difference'], $c['ok'] ? 'ok' : 'MISMATCH'], $r['checks']));
        }

        return $r['mismatches'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
