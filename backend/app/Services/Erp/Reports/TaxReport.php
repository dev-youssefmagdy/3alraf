<?php

namespace App\Services\Erp\Reports;

use App\Models\Erp\Account;
use Illuminate\Support\Facades\DB;

/** VAT: movements on vat_payable by month - collected on sales, reversed on returns/cancellations, settlements and other. */
class TaxReport extends ReportBase
{
    public function run(ErpReportFilter $f): array
    {
        $ids = Account::where('role_key', 'vat_payable')->pluck('id')->all();
        $opening = bcmul($this->debitBalance($ids, $f->dayBeforeFrom(), $f->branchId), '-1', self::S);
        $c = 'erp_journal_lines.credit';
        $d = 'erp_journal_lines.debit';
        $rev = "e.type = 'reversal'";
        $q = $this->lines($f, true, false)->whereIn('a.id', $ids ?: [0])->groupBy(DB::raw("DATE_FORMAT(e.entry_date, '%Y-%m')"))->orderBy('m')
            ->selectRaw("DATE_FORMAT(e.entry_date, '%Y-%m') as m,
                COALESCE(SUM(CASE WHEN e.source_type = 'order' AND NOT ($rev) THEN $c - $d ELSE 0 END),0) as collected,
                COALESCE(SUM(CASE WHEN e.source_type = 'order_return' AND NOT ($rev) THEN $c - $d ELSE 0 END),0) as ret,
                COALESCE(SUM(CASE WHEN e.source_type = 'order' AND ($rev) THEN $c - $d ELSE 0 END),0) as cancelled,
                COALESCE(SUM($c - $d),0) as net");
        $rows = $q->get()->map(fn ($r) => ['month' => $r->m, 'collected' => bcadd((string) $r->collected, '0', 4), 'returns' => bcadd((string) $r->ret, '0', 4), 'cancelled' => bcadd((string) $r->cancelled, '0', 4),
            'other' => bcsub(bcsub(bcsub(bcsub((string) $r->net, (string) $r->collected, 4), (string) $r->ret, 4), (string) $r->cancelled, 4), '0', 4), 'net' => bcadd((string) $r->net, '0', 4)])->all();
        $tot = ['month' => __('erp.f.total'), '_total' => true];
        foreach (['collected', 'returns', 'cancelled', 'other', 'net'] as $k) {
            $tot[$k] = $this->sum($rows, $k);
        }
        $rows[] = $tot;
        $closing = bcadd($opening, $tot['net'], self::S);
        $actual = bcmul($this->debitBalance($ids, $f->to, $f->branchId), '-1', self::S);

        return $this->out('tax', [$this->table([$this->col('month'), $this->col('collected', 'money'), $this->col('returns', 'money'), $this->col('cancelled', 'money'), $this->col('other', 'money'), $this->col('net', 'money')], $rows)],
            [$this->card('opening', $opening), $this->card('collected', $tot['collected']), $this->card('returns', $tot['returns']), $this->card('net_payable', $closing)],
            [], ['opening' => $opening, 'collected' => $tot['collected'], 'returns' => $tot['returns'], 'net' => $tot['net'], 'closing' => $closing, 'balance' => $actual, 'reconciled' => bccomp($closing, $actual, self::S) === 0]);
    }
}
