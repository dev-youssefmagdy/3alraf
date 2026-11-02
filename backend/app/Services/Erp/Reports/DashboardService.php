<?php

namespace App\Services\Erp\Reports;

use App\Models\Erp\JournalEntry;
use Carbon\Carbon;

/** Executive dashboard. KPIs are the SAME figures the statements show (profitLoss / balanceSheet / balancesByRole) - no separate maths. */
class DashboardService extends ReportBase
{
    public function __construct(private LedgerReports $ledger, private BranchReports $branches, private ActivityReports $activity) {}

    public function kpis(ErpReportFilter $f): array
    {
        $pl = $this->ledger->profitLoss($f->from, $f->to, $f->branchId);
        $bs = $this->ledger->balanceSheet($f->to, $f->branchId);
        $bal = $this->ledger->balancesByRole($f->to, $f->branchId);
        $gross = '0';
        foreach ($pl['groups']['revenue'] as $r) {
            if (! in_array($r['account']->role_key, ['sales_returns', 'sales_discounts'], true)) {
                $gross = bcadd($gross, $r['amount'], self::S);
            }
        }
        $cash = '0';
        foreach (self::CASH_ROLES as $role) {
            $cash = bcadd($cash, $bal[$role] ?? '0', self::S);
        }

        return [
            'total_revenue' => $gross, 'net_revenue' => $pl['totals']['revenue'], 'cogs' => $pl['totals']['cogs'], 'gross_profit' => $pl['gross_profit'], 'opex' => $pl['totals']['opex'], 'net_profit' => $pl['net_profit'],
            'cash' => $cash, 'receivable' => $bal['receivable'] ?? '0.0000', 'payable' => $bal['payable'] ?? '0.0000', 'inventory' => $bal['inventory'] ?? '0.0000',
            'equity' => $bs['total_equity'], 'liabilities' => $bs['totals']['liability'],
        ];
    }

    public function trend(ErpReportFilter $f): array
    {
        $start = Carbon::parse($f->to)->startOfMonth()->subMonths(5)->toDateString();
        $q = $this->noClosing($this->lines(new ErpReportFilter($start, $f->to, $f->branchId), true, false))->whereIn('a.type', ['revenue', 'expense'])
            ->groupByRaw("DATE_FORMAT(e.entry_date, '%Y-%m')")->orderBy('m')->selectRaw("DATE_FORMAT(e.entry_date, '%Y-%m') as m,
                COALESCE(SUM(CASE WHEN a.type = 'revenue' THEN erp_journal_lines.credit - erp_journal_lines.debit ELSE 0 END),0) as revenue,
                COALESCE(SUM(CASE WHEN a.type = 'expense' THEN erp_journal_lines.debit - erp_journal_lines.credit ELSE 0 END),0) as expense")->get()->keyBy('m');
        $labels = [];
        $rev = [];
        $exp = [];
        for ($d = Carbon::parse($start); $d->lte(Carbon::parse($f->to)); $d->addMonth()) {
            $k = $d->format('Y-m');
            $labels[] = $k;
            $rev[] = round((float) ($q[$k]->revenue ?? 0), 2);
            $exp[] = round((float) ($q[$k]->expense ?? 0), 2);
        }

        return ['type' => 'line', 'labels' => $labels, 'datasets' => [['label' => __('erp_rep.col.revenue'), 'data' => $rev], ['label' => __('erp_rep.col.expense'), 'data' => $exp]]];
    }

    public function run(ErpReportFilter $f): array
    {
        $names = $this->branchNames();
        $profit = $this->branches->profit($f);
        $bl = [];
        $bd = [];
        foreach ($profit as $b => $x) {
            $bl[] = $b ? ($names[$b] ?? '#'.$b) : __('erp_rep.unallocated');
            $bd[] = round((float) $x['net'], 2);
        }
        $exp = $this->activity->expenses($f)['tables'][0]['rows'];
        $top = array_slice(array_values(array_filter($exp, fn ($r) => empty($r['_total']))), 0, 6);
        $recent = JournalEntry::whereIn('status', ['posted', 'reversed'])->when($f->branchId, fn ($q) => $q->whereIn('id', fn ($s) => $s->select('entry_id')->from('erp_journal_lines')->where('branch_id', $f->branchId)))
            ->orderByDesc('entry_date')->orderByDesc('id')->limit(10)->get(['id', 'number', 'entry_date', 'description', 'total', 'type', 'status']);

        return ['kpis' => $this->kpis($f), 'trend' => $this->trend($f), 'branchChart' => ['type' => 'bar', 'labels' => $bl, 'datasets' => [['label' => __('erp_rep.col.net'), 'data' => $bd]]],
            'expenseChart' => ['type' => 'doughnut', 'labels' => array_column($top, 'account'), 'datasets' => [['label' => __('erp_rep.col.amount'), 'data' => array_map(fn ($r) => round((float) $r['amount'], 2), $top)]]],
            'recent' => $recent];
    }
}
