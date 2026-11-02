<?php

namespace App\Services\Erp\Reports;

use App\Models\Branch;
use App\Models\Erp\Account;
use Illuminate\Support\Facades\DB;

/** Branch profitability (P&L per branch incl. unallocated) and the branch-accounting overview. Branch-specific views need erp.branches.view (controller). */
class BranchReports extends ReportBase
{
    /** @return array<int,array{revenue:string,cogs:string,opex:string,gross:string,net:string}> keyed by branch id (0 = unallocated) */
    public function profit(ErpReportFilter $f): array
    {
        $cogsSub = "COALESCE(a.subtype,'') IN ('cogs','shrinkage')";
        $r = $this->noClosing($this->lines($f, true, false))->whereIn('a.type', ['revenue', 'expense'])->groupBy('erp_journal_lines.branch_id')
            ->selectRaw("erp_journal_lines.branch_id as b,
                COALESCE(SUM(CASE WHEN a.type = 'revenue' THEN erp_journal_lines.credit - erp_journal_lines.debit ELSE 0 END),0) as revenue,
                COALESCE(SUM(CASE WHEN a.type = 'expense' AND $cogsSub THEN erp_journal_lines.debit - erp_journal_lines.credit ELSE 0 END),0) as cogs,
                COALESCE(SUM(CASE WHEN a.type = 'expense' AND NOT ($cogsSub) THEN erp_journal_lines.debit - erp_journal_lines.credit ELSE 0 END),0) as opex")->get();
        $out = [];
        foreach ($r as $x) {
            $gross = bcsub((string) $x->revenue, (string) $x->cogs, self::S);
            $out[(int) $x->b] = ['revenue' => bcadd((string) $x->revenue, '0', self::S), 'cogs' => bcadd((string) $x->cogs, '0', self::S), 'opex' => bcadd((string) $x->opex, '0', self::S),
                'gross' => $gross, 'net' => bcsub($gross, (string) $x->opex, self::S)];
        }

        return $out;
    }

    public function profitability(ErpReportFilter $f): array
    {
        $p = $this->profit($f);
        $br = $this->branchNames();
        $rows = [];
        $ids = $f->branchId ? [$f->branchId] : array_merge(array_keys($br), [0]);
        foreach ($ids as $b) {
            $x = $p[$b] ?? null;
            if (! $x && $b === 0) {
                continue;
            }
            $x ??= ['revenue' => '0.0000', 'cogs' => '0.0000', 'opex' => '0.0000', 'gross' => '0.0000', 'net' => '0.0000'];
            $rows[] = ['branch' => $b ? $br[$b] : __('erp_rep.unallocated')] + $x + ['margin' => bccomp($x['revenue'], '0', self::S) === 0 ? '0.00' : bcdiv(bcmul($x['net'], '100', 6), $x['revenue'], 2)];
        }
        $t = ['branch' => __('erp.f.total'), '_total' => true];
        foreach (['revenue', 'cogs', 'gross', 'opex', 'net'] as $k) {
            $t[$k] = $this->sum($rows, $k);
        }
        $t['margin'] = bccomp($t['revenue'], '0', self::S) === 0 ? '0.00' : bcdiv(bcmul($t['net'], '100', 6), $t['revenue'], 2);
        $rows[] = $t;

        return $this->out('branch-profitability', [$this->table([$this->col('branch'), $this->col('revenue', 'money'), $this->col('cogs', 'money'), $this->col('gross', 'money'), $this->col('opex', 'money'), $this->col('net', 'money'), $this->col('margin', 'pct')], $rows)],
            [$this->card('net', $t['net'])], [], ['total_net' => $t['net'], 'total_revenue' => $t['revenue']]);
    }

    /** Branch list for the Branch Accounting page: P&L, cash, inventory, transfers. */
    public function overview(ErpReportFilter $f): array
    {
        $profit = $this->profit($f);
        $role = fn (array $roles) => "a.role_key IN ({$this->inList($roles)})";
        $bal = $this->lines($f, false, false)->groupBy('erp_journal_lines.branch_id')->selectRaw("erp_journal_lines.branch_id as b,
            COALESCE(SUM(CASE WHEN {$role(self::CASH_ROLES)} THEN erp_journal_lines.debit - erp_journal_lines.credit ELSE 0 END),0) as cash,
            COALESCE(SUM(CASE WHEN {$role(['inventory'])} THEN erp_journal_lines.debit - erp_journal_lines.credit ELSE 0 END),0) as inventory")->get()->keyBy('b');
        $mv = $this->lines($f, true, false)->groupBy('erp_journal_lines.branch_id')->selectRaw("erp_journal_lines.branch_id as b,
            COALESCE(SUM(CASE WHEN {$role(['inventory'])} AND e.source_type = 'stock_transfer' THEN erp_journal_lines.credit ELSE 0 END),0) as inv_out,
            COALESCE(SUM(CASE WHEN {$role(['inventory'])} AND e.source_type = 'stock_transfer' THEN erp_journal_lines.debit ELSE 0 END),0) as inv_in,
            COALESCE(SUM(CASE WHEN {$role(['branch_transfer'])} THEN erp_journal_lines.debit ELSE 0 END),0) as cash_out,
            COALESCE(SUM(CASE WHEN {$role(['branch_transfer'])} THEN erp_journal_lines.credit ELSE 0 END),0) as cash_in")->get()->keyBy('b');
        $rows = [];
        $ids = array_unique(array_merge($f->branchId ? [$f->branchId] : array_keys($this->branchNames()), array_map('intval', array_merge(array_keys($profit), $bal->keys()->all(), $mv->keys()->all()))));
        $names = $this->branchNames();
        foreach ($ids as $b) {
            if ($f->branchId && $b !== $f->branchId) {
                continue;
            }
            $z = '0.0000';
            $rows[] = ['id' => $b, 'name' => $b ? ($names[$b] ?? '#'.$b) : __('erp_rep.unallocated'), 'revenue' => $profit[$b]['revenue'] ?? $z, 'net' => $profit[$b]['net'] ?? $z,
                'cash' => bcadd((string) ($bal[$b]->cash ?? '0'), '0', self::S), 'inventory' => bcadd((string) ($bal[$b]->inventory ?? '0'), '0', self::S),
                'inv_out' => bcadd((string) ($mv[$b]->inv_out ?? '0'), '0', self::S), 'inv_in' => bcadd((string) ($mv[$b]->inv_in ?? '0'), '0', self::S),
                'cash_out' => bcadd((string) ($mv[$b]->cash_out ?? '0'), '0', self::S), 'cash_in' => bcadd((string) ($mv[$b]->cash_in ?? '0'), '0', self::S)];
        }
        usort($rows, fn ($a, $c) => ($a['id'] === 0) <=> ($c['id'] === 0) ?: $a['id'] <=> $c['id']);

        return $rows;
    }
}
