<?php

namespace App\Services\Erp\Reports;

use App\Models\Erp\Account;
use App\Models\Erp\JournalLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Every figure is computed from posted journal lines (no maintained totals). Tenant scope comes from the models. */
class LedgerReports
{
    private const SCALE = 4;

    /** Per-account debit/credit sums of posted (and reversed, whose mirror entries offset them) entries. */
    private function sums(?string $from, ?string $to, ?int $branchId, bool $excludeClosing = false): Collection
    {
        return JournalLine::query()
            ->join('erp_journal_entries as e', 'e.id', '=', 'erp_journal_lines.entry_id')
            ->whereIn('e.status', ['posted', 'reversed'])
            // P&L of a closed year must stay computable: year-end closing entries (and their reversals) are not trading activity
            ->when($excludeClosing, fn ($q) => $q->where('e.type', '!=', 'closing')->whereNotExists(fn ($x) => $x->select(DB::raw(1))->from('erp_journal_entries as o')->whereColumn('o.id', 'e.reverses_id')->where('o.type', 'closing')))
            ->when($from, fn ($q) => $q->where('e.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('e.entry_date', '<=', $to))
            ->when($branchId, fn ($q) => $q->where('erp_journal_lines.branch_id', $branchId))
            ->groupBy('erp_journal_lines.account_id')
            ->select('erp_journal_lines.account_id', DB::raw('SUM(erp_journal_lines.debit) as dr'), DB::raw('SUM(erp_journal_lines.credit) as cr'))
            ->get()->keyBy('account_id');
    }

    private function signed(Account $a, ?object $s): string
    {
        $dr = (string) ($s->dr ?? '0');
        $cr = (string) ($s->cr ?? '0');

        return $a->normal_balance === 'debit' ? bcsub($dr, $cr, self::SCALE) : bcsub($cr, $dr, self::SCALE);
    }

    public function trialBalance(?string $to = null, ?string $from = null, ?int $branchId = null): array
    {
        $sums = $this->sums($from, $to, $branchId);
        $rows = [];
        $tdr = '0';
        $tcr = '0';
        foreach (Account::where('is_group', false)->orderBy('code')->get() as $a) {
            $s = $sums[$a->id] ?? null;
            if (! $s) {
                continue;
            }
            $net = bcsub((string) $s->dr, (string) $s->cr, self::SCALE); // debit-positive
            $dr = bccomp($net, '0', self::SCALE) > 0 ? $net : '0.0000';
            $cr = bccomp($net, '0', self::SCALE) < 0 ? bcmul($net, '-1', self::SCALE) : '0.0000';
            $tdr = bcadd($tdr, $dr, self::SCALE);
            $tcr = bcadd($tcr, $cr, self::SCALE);
            $rows[] = ['account' => $a, 'debit' => $dr, 'credit' => $cr];
        }

        return ['rows' => $rows, 'total_debit' => $tdr, 'total_credit' => $tcr, 'balanced' => bccomp($tdr, $tcr, self::SCALE) === 0];
    }

    /** Running-balance ledger of one account. At most $limit lines are loaded (SQL-ordered, entries eager-loaded); `truncated` tells the page to say so, closing stays the true balance. */
    public function generalLedger(Account $account, ?string $from = null, ?string $to = null, ?int $branchId = null, int $limit = 5000): array
    {
        $opening = '0';
        if ($from) {
            $s = $this->sums(null, date('Y-m-d', strtotime($from.' -1 day')), $branchId)[$account->id] ?? null;
            $opening = $this->signed($account, $s);
        }
        $lines = JournalLine::query()->join('erp_journal_entries as e', 'e.id', '=', 'erp_journal_lines.entry_id')->with('entry')
            ->where('erp_journal_lines.account_id', $account->id)->whereIn('e.status', ['posted', 'reversed'])
            ->when($from, fn ($q) => $q->where('e.entry_date', '>=', $from))->when($to, fn ($q) => $q->where('e.entry_date', '<=', $to))
            ->when($branchId, fn ($q) => $q->where('erp_journal_lines.branch_id', $branchId))
            ->orderBy('e.entry_date')->orderBy('erp_journal_lines.id')->select('erp_journal_lines.*')->limit($limit + 1)->get();
        $truncated = $lines->count() > $limit;
        $lines = $lines->take($limit);
        $run = $opening;
        $rows = $lines->map(function ($l) use ($account, &$run) {
            $d = $account->normal_balance === 'debit' ? bcsub((string) $l->debit, (string) $l->credit, self::SCALE) : bcsub((string) $l->credit, (string) $l->debit, self::SCALE);
            $run = bcadd($run, $d, self::SCALE);

            return ['line' => $l, 'balance' => $run];
        });
        $closing = $truncated ? $this->signed($account, $this->sums(null, $to, $branchId)[$account->id] ?? null) : $run;

        return ['account' => $account, 'opening' => $opening, 'rows' => $rows, 'closing' => $closing, 'truncated' => $truncated, 'limit' => $limit];
    }

    /** Signed (normal-balance) total per account role_key over posted lines, closing entries INCLUDED (as the balance sheet sees them). @return array<string,string> */
    public function balancesByRole(?string $to = null, ?int $branchId = null): array
    {
        $sums = $this->sums(null, $to, $branchId);
        $out = [];
        foreach (Account::where('is_group', false)->get() as $a) {
            if (($s = $sums[$a->id] ?? null) && $a->role_key) {
                $out[$a->role_key] = bcadd($out[$a->role_key] ?? '0', $this->signed($a, $s), self::SCALE);
            }
        }

        return $out;
    }

    /** Residual (unclosed) earnings = revenue - expense balances through $to, closing entries included. */
    public function residualEarnings(?string $to, ?int $branchId = null): string
    {
        $sums = $this->sums(null, $to, $branchId);
        $e = '0';
        foreach (Account::whereIn('type', ['revenue', 'expense'])->where('is_group', false)->get() as $a) {
            if ($s = $sums[$a->id] ?? null) {
                $e = $a->type === 'revenue' ? bcadd($e, $this->signed($a, $s), self::SCALE) : bcsub($e, $this->signed($a, $s), self::SCALE);
            }
        }

        return $e;
    }

    /** Account/branch balances (debit-positive) of revenue+expense accounts through $to incl. closing entries - the amounts a year-end close must clear. @return array<int,array{account:Account,branch_id:?int,net:string}> */
    public function openPlBalances(string $to): array
    {
        $rows = JournalLine::query()->join('erp_journal_entries as e', 'e.id', '=', 'erp_journal_lines.entry_id')->whereIn('e.status', ['posted', 'reversed'])->where('e.entry_date', '<=', $to)
            ->join('erp_accounts as a', 'a.id', '=', 'erp_journal_lines.account_id')->whereIn('a.type', ['revenue', 'expense'])
            ->groupBy('erp_journal_lines.account_id', 'erp_journal_lines.branch_id')
            ->select('erp_journal_lines.account_id', 'erp_journal_lines.branch_id', DB::raw('SUM(erp_journal_lines.debit) - SUM(erp_journal_lines.credit) as net'))->get();
        $accounts = Account::whereIn('id', $rows->pluck('account_id'))->get()->keyBy('id');
        $out = [];
        foreach ($rows as $r) {
            $net = bcadd((string) $r->net, '0', self::SCALE);
            if (bccomp($net, '0', self::SCALE) !== 0) {
                $out[] = ['account' => $accounts[$r->account_id], 'branch_id' => $r->branch_id ? (int) $r->branch_id : null, 'net' => $net];
            }
        }

        return $out;
    }

    public function profitLoss(?string $from, ?string $to, ?int $branchId = null): array
    {
        $sums = $this->sums($from, $to, $branchId, true);
        $groups = ['revenue' => [], 'cogs' => [], 'opex' => []];
        $tot = ['revenue' => '0', 'cogs' => '0', 'opex' => '0'];
        foreach (Account::whereIn('type', ['revenue', 'expense'])->where('is_group', false)->orderBy('code')->get() as $a) {
            $s = $sums[$a->id] ?? null;
            if (! $s) {
                continue;
            }
            $v = $this->signed($a, $s);
            $g = $a->type === 'revenue' ? 'revenue' : (in_array($a->subtype, ['cogs', 'shrinkage'], true) ? 'cogs' : 'opex');
            $groups[$g][] = ['account' => $a, 'amount' => $v];
            $tot[$g] = bcadd($tot[$g], $v, self::SCALE);
        }
        $gross = bcsub($tot['revenue'], $tot['cogs'], self::SCALE);

        return ['groups' => $groups, 'totals' => $tot, 'gross_profit' => $gross, 'net_profit' => bcsub($gross, $tot['opex'], self::SCALE)];
    }

    public function balanceSheet(?string $asOf = null, ?int $branchId = null): array
    {
        $sums = $this->sums(null, $asOf, $branchId);
        $sec = ['asset' => [], 'liability' => [], 'equity' => []];
        $tot = ['asset' => '0', 'liability' => '0', 'equity' => '0'];
        $earn = '0';
        foreach (Account::where('is_group', false)->orderBy('code')->get() as $a) {
            $s = $sums[$a->id] ?? null;
            if (! $s) {
                continue;
            }
            $v = $this->signed($a, $s);
            if ($a->type === 'revenue') {
                $earn = bcadd($earn, $v, self::SCALE);
            } elseif ($a->type === 'expense') {
                $earn = bcsub($earn, $v, self::SCALE);
            } else {
                $sec[$a->type][] = ['account' => $a, 'amount' => $v];
                $tot[$a->type] = bcadd($tot[$a->type], $v, self::SCALE);
            }
        }
        $equity = bcadd($tot['equity'], $earn, self::SCALE);

        return ['sections' => $sec, 'totals' => $tot, 'current_earnings' => $earn, 'total_equity' => $equity,
            'balanced' => bccomp($tot['asset'], bcadd($tot['liability'], $equity, self::SCALE), self::SCALE) === 0];
    }
}
