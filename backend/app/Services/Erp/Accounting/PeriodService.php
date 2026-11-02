<?php

namespace App\Services\Erp\Accounting;

use App\Exceptions\AccountingException;
use App\Models\Erp\AuditLog;
use App\Models\Erp\FiscalYear;
use App\Models\Erp\JournalEntry;
use App\Models\Erp\Period;
use App\Services\Erp\Reports\LedgerReports;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PeriodService
{
    /** Open period for a date (creating the calendar fiscal year with monthly periods on first use). Throws if the period is closed. */
    public function openPeriodFor(Carbon|string $date, bool $lock = false): Period
    {
        $d = Carbon::parse($date)->startOfDay();
        $period = Period::where('starts_on', '<=', $d)->where('ends_on', '>=', $d)->first() ?? $this->createYear($d->year)->periods()->where('starts_on', '<=', $d)->where('ends_on', '>=', $d)->firstOrFail();
        if ($lock) {
            $period = Period::whereKey($period->id)->lockForUpdate()->firstOrFail(); // re-read the status under the lock
        }
        if (! $period->isOpen()) {
            throw new AccountingException(__('erp.errors.period_closed', ['period' => $period->name]));
        }

        return $period;
    }

    public function createYear(int $year): FiscalYear
    {
        return DB::transaction(function () use ($year) {
            $fy = FiscalYear::firstOrCreate(['starts_on' => "$year-01-01"], ['name' => (string) $year, 'ends_on' => "$year-12-31", 'status' => 'open']);
            for ($m = 1; $m <= 12; $m++) {
                $start = Carbon::create($year, $m, 1);
                Period::firstOrCreate(['starts_on' => $start->toDateString()], ['fiscal_year_id' => $fy->id, 'name' => $start->format('Y-m'), 'ends_on' => $start->copy()->endOfMonth()->toDateString(), 'status' => 'open']);
            }

            return $fy;
        });
    }

    public function close(Period $period, ?int $userId): Period
    {
        return DB::transaction(function () use ($period, $userId) {
            $period = Period::whereKey($period->id)->lockForUpdate()->firstOrFail();
            if ($period->status === 'closed') {
                return $period; // double submit: already closed
            }
            if (Period::where('starts_on', '<', $period->starts_on)->where('status', 'open')->exists()) {
                throw new AccountingException(__('erp.errors.close_previous_first'));
            }
            // drafts get their period only when posted, so they are matched by entry date
            if (JournalEntry::where('status', 'draft')->whereBetween('entry_date', [$period->starts_on->toDateString(), $period->ends_on->toDateString()])->exists()) {
                throw new AccountingException(__('erp.errors.drafts_in_period'));
            }
            $period->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $userId]);
            AuditLog::create(['action' => 'period.closed', 'subject_type' => 'period', 'subject_id' => $period->id, 'user_id' => $userId, 'meta' => ['name' => $period->name]]);

            return $period;
        });
    }

    public function reopen(Period $period, ?int $userId, string $reason): Period
    {
        if (trim($reason) === '') {
            throw new AccountingException(__('erp.errors.reason_required'));
        }
        if ($period->fiscalYear?->status === 'closed') {
            throw new AccountingException(__('erp.errors.period_in_closed_year'));
        }

        return DB::transaction(function () use ($period, $userId, $reason) {
            $period = Period::whereKey($period->id)->lockForUpdate()->firstOrFail();
            if ($period->status === 'open') {
                return $period;
            }
            $period->update(['status' => 'open', 'closed_at' => null, 'closed_by' => null]);
            AuditLog::create(['action' => 'period.reopened', 'subject_type' => 'period', 'subject_id' => $period->id, 'user_id' => $userId, 'meta' => ['name' => $period->name, 'reason' => $reason]]);

            return $period;
        });
    }

    /**
     * Year-end close (docs/ERP_PLAN.md "Year-end closing"): entry 1 (type closing, dated the last day of the year) clears every revenue/expense
     * account/branch balance into income_summary; entry 2 clears income_summary into retained_earnings. Then all open periods are closed and the
     * year is locked. Idempotent per close run; a second close of a closed year is refused.
     */
    public function closeYear(FiscalYear $fy, ?int $userId): FiscalYear
    {
        return DB::transaction(function () use ($fy, $userId) {
            $fy = FiscalYear::whereKey($fy->id)->lockForUpdate()->firstOrFail();
            if ($fy->status === 'closed') {
                throw new AccountingException(__('erp.errors.year_closed'));
            }
            if (FiscalYear::where('starts_on', '<', $fy->starts_on)->where('status', 'open')->exists()) {
                throw new AccountingException(__('erp.errors.close_previous_year_first'));
            }
            $from = $fy->starts_on->toDateString();
            $to = $fy->ends_on->toDateString();
            if (JournalEntry::where('status', 'draft')->whereBetween('entry_date', [$from, $to])->exists()) {
                throw new AccountingException(__('erp.errors.drafts_in_period'));
            }
            $last = $fy->periods()->get()->last();
            if (! $last || ! $last->isOpen()) {
                throw new AccountingException(__('erp.errors.year_last_period_closed'));
            }
            $journals = app(JournalService::class);
            $run = JournalEntry::where('source_type', 'fiscal_year')->where('source_id', $fy->id)->where('type', 'closing')->count();
            $base = ['entry_date' => $to, 'type' => 'closing', 'source_type' => 'fiscal_year', 'source_id' => $fy->id, 'created_by' => $userId];

            $lines = [];
            $perBranch = [];
            foreach (app(LedgerReports::class)->openPlBalances($to) as $r) {
                $n = $r['net']; // debit-positive
                $lines[] = ['account' => $r['account']->id, 'branch_id' => $r['branch_id'], 'memo' => __('erp.year_close_memo'),
                    (bccomp($n, '0', 4) > 0 ? 'credit' : 'debit') => ltrim($n, '-')];
                $perBranch[$r['branch_id'] ?? 0] = bcadd($perBranch[$r['branch_id'] ?? 0] ?? '0', $n, 4);
            }
            foreach ($perBranch as $b => $sum) {
                if (bccomp($sum, '0', 4) !== 0) {
                    $lines[] = ['account' => 'income_summary', 'branch_id' => $b ?: null, 'memo' => __('erp.year_close_memo'), (bccomp($sum, '0', 4) > 0 ? 'debit' : 'credit') => ltrim($sum, '-')];
                }
            }
            if ($lines) {
                $journals->post($base + ['description' => __('erp.year_close_desc', ['year' => $fy->name]), 'idempotency_key' => "fy:{$fy->id}:close:$run:pl"], $lines);
            }

            // income summary -> retained earnings (whatever is left, including profit already moved by distributions)
            $is = app(AccountResolver::class)->id('income_summary');
            $rows = \App\Models\Erp\JournalLine::query()->join('erp_journal_entries as e', 'e.id', '=', 'erp_journal_lines.entry_id')->whereIn('e.status', ['posted', 'reversed'])
                ->where('e.entry_date', '<=', $to)->where('erp_journal_lines.account_id', $is)->groupBy('erp_journal_lines.branch_id')
                ->select('erp_journal_lines.branch_id', DB::raw('SUM(erp_journal_lines.credit) - SUM(erp_journal_lines.debit) as c'))->get();
            $re = [];
            foreach ($rows as $r) {
                $c = bcadd((string) $r->c, '0', 4); // credit-positive
                if (bccomp($c, '0', 4) !== 0) {
                    $abs = ltrim($c, '-');
                    $pos = bccomp($c, '0', 4) > 0;
                    $br = $r->branch_id ? (int) $r->branch_id : null;
                    $re[] = ['account' => 'income_summary', 'branch_id' => $br, (($pos) ? 'debit' : 'credit') => $abs, 'memo' => __('erp.year_close_memo')];
                    $re[] = ['account' => 'retained_earnings', 'branch_id' => $br, (($pos) ? 'credit' : 'debit') => $abs, 'memo' => __('erp.year_close_memo')];
                }
            }
            if ($re) {
                $journals->post($base + ['description' => __('erp.year_close_re', ['year' => $fy->name]), 'idempotency_key' => "fy:{$fy->id}:close:$run:re"], $re);
            }

            foreach ($fy->periods()->where('status', 'open')->get() as $p) {
                $this->close($p, $userId);
            }
            $fy->update(['status' => 'closed']);
            AuditLog::create(['action' => 'year.closed', 'subject_type' => 'fiscal_year', 'subject_id' => $fy->id, 'user_id' => $userId, 'meta' => ['name' => $fy->name]]);

            return $fy;
        });
    }

    /** Reopen a closed year (latest first): periods reopen, the closing entries are REVERSED (never edited), reason is audited. */
    public function reopenYear(FiscalYear $fy, ?int $userId, string $reason): FiscalYear
    {
        if (trim($reason) === '') {
            throw new AccountingException(__('erp.errors.reason_required'));
        }

        return DB::transaction(function () use ($fy, $userId, $reason) {
            $fy = FiscalYear::whereKey($fy->id)->lockForUpdate()->firstOrFail();
            if ($fy->status !== 'closed') {
                throw new AccountingException(__('erp.errors.year_not_closed'));
            }
            if (FiscalYear::where('starts_on', '>', $fy->starts_on)->where('status', 'closed')->exists()) {
                throw new AccountingException(__('erp.errors.year_reopen_latest'));
            }
            if (\App\Models\Erp\ProfitDistribution::where('fiscal_year_id', $fy->id)->where('status', '!=', 'cancelled')->exists()) {
                throw new AccountingException(__('erp.errors.year_has_distributions'));
            }
            $fy->update(['status' => 'open']);
            foreach ($fy->periods()->get() as $p) {
                $p->update(['status' => 'open', 'closed_at' => null, 'closed_by' => null]);
            }
            $journals = app(JournalService::class);
            foreach (JournalEntry::where('source_type', 'fiscal_year')->where('source_id', $fy->id)->where('type', 'closing')->where('status', 'posted')->orderByDesc('id')->get() as $e) {
                $journals->reverse($e, $userId, $fy->ends_on->toDateString(), $reason);
            }
            foreach ($fy->periods()->get() as $p) {
                AuditLog::create(['action' => 'period.reopened', 'subject_type' => 'period', 'subject_id' => $p->id, 'user_id' => $userId, 'meta' => ['name' => $p->name, 'reason' => $reason]]);
            }
            AuditLog::create(['action' => 'year.reopened', 'subject_type' => 'fiscal_year', 'subject_id' => $fy->id, 'user_id' => $userId, 'meta' => ['name' => $fy->name, 'reason' => $reason]]);

            return $fy;
        });
    }
}
