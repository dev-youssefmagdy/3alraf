<?php

namespace App\Console\Commands;

use App\Services\Erp\Reconciliation\LedgerVerifier;
use Illuminate\Console\Command;

class ErpVerify extends Command
{
    protected $signature = 'erp:verify {--json : Machine-readable output}';

    protected $description = 'Ledger integrity audit (balanced entries, cached balances, orphan lines, unique keys); exits non-zero on any failure';

    public function handle(LedgerVerifier $verifier): int
    {
        $r = $verifier->run();
        if ($this->option('json')) {
            $this->line(json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->{$r['ok'] ? 'info' : 'error'}($r['ok'] ? 'OK' : $r['failures'].' failing check(s)');
            foreach ($r['checks'] as $key => $c) {
                $this->line(sprintf('  %-28s %s%s', $key, $c['ok'] ? 'ok' : 'FAIL', $c['ok'] ? '' : " ({$c['count']}) e.g. ".json_encode($c['samples'][0] ?? null)));
            }
        }

        return $r['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
