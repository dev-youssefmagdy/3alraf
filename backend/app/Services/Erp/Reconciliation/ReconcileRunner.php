<?php

namespace App\Services\Erp\Reconciliation;

use App\Services\Erp\Backfill\OpeningState;
use App\Services\Erp\Reports\ErpReportFilter;
use App\Services\Erp\Reports\ReconciliationReport;

/** Runs the Reconciliation report (period = opening date .. today, so opening balances and period P&L are compared on equal terms). */
class ReconcileRunner
{
    public function __construct(private ReconciliationReport $report) {}

    /** @return array{from:string,to:string,mismatches:int,checks:array<int,array>} */
    public function run(): array
    {
        $from = OpeningState::openingDate() ?? '2000-01-01';
        $to = now()->toDateString();
        $out = $this->report->run(new ErpReportFilter($from, $to));
        $rows = $out['tables'][0]['rows'];
        $checks = [];
        foreach ($out['meta']['ok'] as $key => $ok) {
            $row = array_shift($rows);
            $checks[] = ['key' => $key, 'expected' => $row['expected'], 'actual' => $row['actual'], 'difference' => $row['difference'], 'ok' => $ok];
        }

        return ['from' => $from, 'to' => $to, 'mismatches' => (int) $out['meta']['mismatches'], 'checks' => $checks];
    }
}
