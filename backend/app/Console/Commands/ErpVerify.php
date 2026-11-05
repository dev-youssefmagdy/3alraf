<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Erp\Reconciliation\LedgerVerifier;
use Illuminate\Console\Command;

class ErpVerify extends Command
{
    protected $signature = 'erp:verify {--tenant= : Tenant slug (default all)} {--json : Machine-readable output}';

    protected $description = 'Ledger integrity audit per tenant (balanced entries, cached balances, orphan lines, unique keys); exits non-zero on any failure';

    public function handle(LedgerVerifier $verifier): int
    {
        $tenants = Tenant::query()->when($this->option('tenant'), fn ($q, $s) => $q->where('slug', $s))->get();
        if ($tenants->isEmpty()) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }
        $all = [];
        $bad = 0;
        foreach ($tenants as $t) {
            $all[$t->slug] = $r = $verifier->run($t);
            $bad += $r['failures'];
        }
        if ($this->option('json')) {
            $this->line(json_encode(['ok' => $bad === 0, 'failures' => $bad, 'tenants' => $all], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            foreach ($all as $slug => $r) {
                $this->{$r['ok'] ? 'info' : 'error'}("== $slug: ".($r['ok'] ? 'OK' : $r['failures'].' failing check(s)'));
                foreach ($r['checks'] as $key => $c) {
                    $this->line(sprintf('  %-28s %s%s', $key, $c['ok'] ? 'ok' : 'FAIL', $c['ok'] ? '' : " ({$c['count']}) e.g. ".json_encode($c['samples'][0] ?? null)));
                }
            }
        }

        return $bad === 0 ? self::SUCCESS : self::FAILURE;
    }
}
