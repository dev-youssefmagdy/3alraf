<?php

namespace App\Services\Erp\Reports;

use App\Models\Erp\Account;
use App\Models\Erp\JournalLine;
use Illuminate\Support\Facades\DB;

/**
 * Direct-method cash flow. Cash-like = cash, bank, pos_cash, gateway, wallet. Each entry's net cash movement is attributed to its non-cash
 * counter lines (a counter line credited = cash in), then classified by counter account: investing (fixed assets), financing (equity,
 * loans, owner distributions), transfers (inter-branch clearing, nets to zero consolidated) and operating (everything else).
 * opening + net change = closing holds by construction because every entry is balanced.
 */
class CashFlowReport extends ReportBase
{
    public const INVESTING = ['fixed_assets', 'accumulated_depreciation'];
    public const FINANCING = ['owner_capital', 'owner_drawings', 'branch_capital', 'retained_earnings', 'distributions_payable', 'reserves', 'income_summary', 'loans', 'opening_equity'];
    public const TRANSFERS = ['branch_transfer'];

    public function section(?string $role): string
    {
        return in_array($role, self::INVESTING, true) ? 'investing' : (in_array($role, self::FINANCING, true) ? 'financing' : (in_array($role, self::TRANSFERS, true) ? 'transfers' : 'operating'));
    }

    public function run(ErpReportFilter $f): array
    {
        $cashIds = Account::whereIn('role_key', self::CASH_ROLES)->pluck('id')->all();
        $opening = $this->debitBalance($cashIds, $f->dayBeforeFrom(), $f->branchId);
        $closing = $this->debitBalance($cashIds, $f->to, $f->branchId);

        $perEntry = $cashIds ? $this->lines($f, true, false)->whereIn('a.id', $cashIds)->groupBy('e.id')->selectRaw('e.id as eid, SUM(erp_journal_lines.debit - erp_journal_lines.credit) as d')->pluck('d', 'eid')->all() : [];
        $buckets = ['operating' => [], 'investing' => [], 'financing' => [], 'transfers' => []];
        $netChange = '0';
        foreach (array_chunk(array_keys($perEntry), 1000, true) as $chunk) {
            $counters = JournalLine::query()->join('erp_accounts as a', 'a.id', '=', 'erp_journal_lines.account_id')->whereIn('erp_journal_lines.entry_id', $chunk)->whereNotIn('a.id', $cashIds)
                ->get(['erp_journal_lines.entry_id', 'a.id as aid', 'a.code', 'a.name', 'a.role_key', DB::raw('(erp_journal_lines.credit - erp_journal_lines.debit) as c')])->groupBy('entry_id');
            foreach ($chunk as $eid) {
                $d = bcadd((string) $perEntry[$eid], '0', self::S);
                if (bccomp($d, '0', self::S) === 0) {
                    continue;
                }
                $netChange = bcadd($netChange, $d, self::S);
                $cl = $counters[$eid] ?? collect();
                $all = $cl->reduce(fn ($c, $l) => bcadd($c, (string) $l->c, self::S), '0');
                if (bccomp($all, '0', self::S) === 0 || $cl->isEmpty()) { // internal move between cash accounts/branches
                    $this->add($buckets, 'transfers', 0, '', __('erp_rep.cf_internal'), $d);
                    continue;
                }
                $left = $d;
                $n = $cl->count();
                foreach ($cl->values() as $i => $l) {
                    $share = $i === $n - 1 ? $left : bcadd(bcdiv(bcmul((string) $l->c, $d, 8), $all, 8), '0', self::S);
                    $left = bcsub($left, $share, self::S);
                    $this->add($buckets, $this->section($l->role_key), (int) $l->aid, (string) $l->code, $this->localName($l->name), $share);
                }
            }
        }

        $rows = [];
        $sectionTotals = [];
        foreach ($buckets as $sec => $items) {
            $rows[] = ['section' => __('erp_rep.cf.'.$sec), '_head' => true];
            ksort($items);
            $tot = '0';
            foreach ($items as $it) {
                if (bccomp($it['amount'], '0', self::S) === 0) {
                    continue;
                }
                $rows[] = ['code' => $it['code'], 'account' => $it['name'], 'amount' => $it['amount']];
                $tot = bcadd($tot, $it['amount'], self::S);
            }
            $tot = bcadd($tot, '0', self::S);
            $sectionTotals[$sec] = $tot;
            $rows[] = ['section' => __('erp_rep.cf.net_'.$sec), 'amount' => $tot, '_total' => true];
        }
        $rows[] = ['section' => __('erp_rep.cf.net_change'), 'amount' => $netChange, '_total' => true];
        $rows[] = ['section' => __('erp_rep.cf.opening'), 'amount' => $opening];
        $rows[] = ['section' => __('erp_rep.cf.closing'), 'amount' => $closing, '_total' => true];
        $ok = bccomp(bcadd($opening, $netChange, self::S), $closing, self::S) === 0;

        return $this->out('cash-flow', [$this->table([$this->col('section'), $this->col('code'), $this->col('account'), $this->col('amount', 'money')], $rows)],
            [$this->card('opening', $opening), $this->card('net_change', $netChange), $this->card('closing', $closing)],
            $ok ? [] : [__('erp_rep.cf.not_reconciled')],
            ['opening' => $opening, 'net_change' => $netChange, 'closing' => $closing, 'sections' => $sectionTotals, 'reconciled' => $ok]);
    }

    private function add(array &$b, string $sec, int $aid, string $code, string $name, string $amt): void
    {
        $k = $code.'|'.$aid;
        $b[$sec][$k] ??= ['code' => $code, 'name' => $name, 'amount' => '0'];
        $b[$sec][$k]['amount'] = bcadd($b[$sec][$k]['amount'], $amt, self::S);
    }

    private function localName(mixed $n): string
    {
        $a = is_string($n) ? json_decode($n, true) : $n;

        return is_array($a) ? (string) ($a[app()->getLocale()] ?? $a['en'] ?? reset($a)) : (string) $n;
    }
}
