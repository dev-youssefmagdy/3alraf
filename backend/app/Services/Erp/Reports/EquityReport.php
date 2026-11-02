<?php

namespace App\Services\Erp\Reports;

use App\Models\Erp\Account;
use App\Models\Erp\Owner;

/** Owner equity: movement per equity account (+ period earnings) and per-owner capital/drawings/distributions, from lines. */
class EquityReport extends ReportBase
{
    public function __construct(private LedgerReports $ledger) {}

    private function name(mixed $n): string
    {
        $a = is_string($n) ? json_decode($n, true) : $n;

        return is_array($a) ? (string) ($a[app()->getLocale()] ?? $a['en'] ?? reset($a)) : (string) $n;
    }

    public function run(ErpReportFilter $f): array
    {
        $accs = Account::where('type', 'equity')->where('is_group', false)->orderBy('code')->get();
        $open = $this->lines($f, false, false, false)->where('e.entry_date', '<', $f->from)->where('a.type', 'equity')->groupBy('a.id')->selectRaw('a.id, SUM(erp_journal_lines.credit - erp_journal_lines.debit) as net')->pluck('net', 'id');
        $mv = $this->lines($f, true, false)->where('a.type', 'equity')->groupBy('a.id')->selectRaw('a.id, SUM(erp_journal_lines.debit) as dr, SUM(erp_journal_lines.credit) as cr')->get()->keyBy('id');
        $rows = [];
        $tot = ['opening' => '0', 'debit' => '0', 'credit' => '0', 'closing' => '0'];
        foreach ($accs as $a) {
            $o = bcadd((string) ($open[$a->id] ?? '0'), '0', self::S);
            $dr = (string) ($mv[$a->id]->dr ?? '0');
            $cr = (string) ($mv[$a->id]->cr ?? '0');
            $c = bcadd(bcsub($cr, $dr, self::S), $o, self::S);
            if (bccomp($o, '0', self::S) === 0 && bccomp($dr, '0', self::S) === 0 && bccomp($cr, '0', self::S) === 0) {
                continue;
            }
            $rows[] = ['code' => $a->code, 'account' => $this->name($a->name), 'opening' => $o, 'debit' => bcadd($dr, '0', self::S), 'credit' => bcadd($cr, '0', self::S), 'closing' => $c];
            foreach (['opening' => $o, 'debit' => $dr, 'credit' => $cr, 'closing' => $c] as $k => $v) {
                $tot[$k] = bcadd($tot[$k], $v, self::S);
            }
        }
        $earn = $this->ledger->profitLoss($f->from, $f->to, $f->branchId)['net_profit'];
        $rows[] = ['code' => '', 'account' => __('erp_rep.period_earnings'), 'credit' => $earn, 'closing' => $earn];
        $total = bcadd($tot['closing'], $earn, self::S);
        $rows[] = ['code' => __('erp.f.total'), 'opening' => $tot['opening'], 'debit' => $tot['debit'], 'credit' => $tot['credit'], 'closing' => $total, '_total' => true];

        // per owner (party owner lines on the equity accounts)
        $own = $this->lines($f, false, false, true)->where('erp_journal_lines.party_type', 'owner')->whereIn('a.role_key', ['owner_capital', 'owner_drawings', 'retained_earnings', 'distributions_payable'])
            ->groupBy('erp_journal_lines.party_id', 'a.role_key')->selectRaw('erp_journal_lines.party_id as pid, a.role_key as role, SUM(erp_journal_lines.credit - erp_journal_lines.debit) as net')->get()->groupBy('pid');
        $names = Owner::whereIn('id', $own->keys()->all())->pluck('name', 'id');
        $orows = [];
        foreach ($own as $pid => $g) {
            $by = $g->pluck('net', 'role');
            $capital = bcadd((string) ($by['owner_capital'] ?? '0'), '0', self::S);
            $draw = bcmul((string) ($by['owner_drawings'] ?? '0'), '-1', self::S);
            $dist = bcmul((string) ($by['retained_earnings'] ?? '0'), '-1', self::S);
            $orows[] = ['owner' => (string) ($names[$pid] ?? '#'.$pid), 'capital' => $capital, 'drawings' => bcadd($draw, '0', self::S), 'distributions' => bcadd($dist, '0', self::S),
                'balance' => bcsub(bcsub($capital, $draw, self::S), $dist, self::S)];
        }
        $orows[] = ['owner' => __('erp.f.total'), '_total' => true] + array_combine(['capital', 'drawings', 'distributions', 'balance'], array_map(fn ($k) => $this->sum($orows, $k), ['capital', 'drawings', 'distributions', 'balance']));

        return $this->out('owner-equity', [
            $this->table([$this->col('code'), $this->col('account'), $this->col('opening', 'money'), $this->col('debit', 'money'), $this->col('credit', 'money'), $this->col('closing', 'money')], $rows),
            $this->table([$this->col('owner'), $this->col('capital', 'money'), $this->col('drawings', 'money'), $this->col('distributions', 'money'), $this->col('balance', 'money')], $orows, __('erp_rep.per_owner')),
        ], [$this->card('closing', $total)], [], ['total_equity' => $total, 'earnings' => $earn]);
    }
}
