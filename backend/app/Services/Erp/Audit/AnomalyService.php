<?php

namespace App\Services\Erp\Audit;

use App\DTOs\Reports\ReportFilter;
use App\Services\Reports\ReportService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ledger anomaly checks for the ERP hub and the Anomalies page. Every check is ONE SQL query, except the optional "deep" net-profit
 * comparison which needs the legacy report. Row: code, severity (high|medium|low), count, entry_id (a sample journal entry or null), detail.
 */
class AnomalyService
{
    public const CODES = ['unbalanced_entry', 'old_draft', 'posting_failed', 'backdated_entry', 'negative_cash', 'negative_inventory', 'negative_stock',
        'control_mismatch', 'order_without_journal', 'large_manual', 'period_reopened', 'profit_mismatch'];

    public const DRAFT_DAYS = 7;

    public const BACKDATE_GRACE_DAYS = 3;

    public const CASH_ROLES = ['cash', 'bank', 'pos_cash', 'gateway', 'wallet'];

    private const SEVERITY = ['unbalanced_entry' => 'high', 'negative_cash' => 'high', 'order_without_journal' => 'high', 'control_mismatch' => 'high',
        'posting_failed' => 'medium', 'backdated_entry' => 'medium', 'negative_inventory' => 'medium', 'negative_stock' => 'medium', 'period_reopened' => 'medium',
        'old_draft' => 'low', 'large_manual' => 'low', 'profit_mismatch' => 'low'];

    public static function severityOf(string $code): string
    {
        return self::SEVERITY[$code] ?? 'low';
    }

    public static function largeManualThreshold(): string
    {
        return (string) config('erp.large_manual_threshold', '100000');
    }

    /** @return Collection<int,array> */
    public function detect(bool $deep = false): Collection
    {
        $rows = collect()
            ->merge($this->unbalanced())->merge($this->oldDrafts())->merge($this->postingFailed())
            ->merge($this->backdated())->merge($this->negativeBalances())->merge($this->negativeStock())
            ->merge($this->controlMismatch())->merge($this->ordersWithoutJournal())->merge($this->largeManual())
            ->merge($this->reopened());
        if ($deep) {
            $rows = $rows->merge($this->profitMismatch());
        }
        $order = ['high' => 0, 'medium' => 1, 'low' => 2];

        return $rows->sortBy(fn ($r) => [$order[$r['severity']], $r['code']])->values();
    }

    /** Total anomaly count. */
    public function count(bool $deep = false): int
    {
        return (int) $this->detect($deep)->sum('count');
    }

    private function row(string $code, int $count, ?int $entry = null, ?string $detail = null): array
    {
        return ['code' => $code, 'severity' => self::severityOf($code), 'count' => $count, 'entry_id' => $entry, 'detail' => $detail];
    }

    /** One row (count + smallest id as sample) when the query matches anything. */
    private function grouped($q, string $code, string $idCol): Collection
    {
        $r = $q->selectRaw("COUNT(*) as c, MIN($idCol) as sample")->first();

        return $r && (int) $r->c > 0 ? collect([$this->row($code, (int) $r->c, $r->sample ? (int) $r->sample : null)]) : collect();
    }

    private function unbalanced(): Collection
    {
        $ids = DB::table('erp_journal_lines as l')->join('erp_journal_entries as e', 'e.id', '=', 'l.entry_id')->whereIn('e.status', ['posted', 'reversed'])
            ->groupBy('e.id')->havingRaw('ROUND(SUM(l.debit) - SUM(l.credit), 4) <> 0')->pluck('e.id');

        return $ids->isEmpty() ? collect() : collect([$this->row('unbalanced_entry', $ids->count(), (int) $ids->min())]);
    }

    private function oldDrafts(): Collection
    {
        $q = DB::table('erp_journal_entries as e')->where('e.status', 'draft')->where('e.created_at', '<', now()->subDays(self::DRAFT_DAYS));

        return $this->grouped($q, 'old_draft', 'e.id');
    }

    private function postingFailed(): Collection
    {
        $q = DB::table('erp_audit_logs as a')->where('a.action', 'posting.failed')->where('a.created_at', '>=', now()->subDays(30))
            ->whereNotExists(fn ($x) => $x->select(DB::raw(1))->from('erp_audit_logs as r')->where('r.action', 'posting.resolved')
                ->whereColumn('r.subject_type', 'a.subject_type')->whereColumn('r.subject_id', 'a.subject_id')->whereColumn('r.id', '>', 'a.id')); // retried successfully

        return $this->grouped($q, 'posting_failed', 'a.id')->map(fn ($r) => ['entry_id' => null] + $r);
    }

    /** Posted more than a few days after the end of the period it landed in (period was open after the fact or entry re-dated). */
    private function backdated(): Collection
    {
        $q = DB::table('erp_journal_entries as e')->join('erp_periods as p', 'p.id', '=', 'e.period_id')->whereIn('e.status', ['posted', 'reversed'])
            ->where('e.type', '!=', 'closing')->whereNotNull('e.posted_at')
            ->whereRaw('DATEDIFF(DATE(e.posted_at), p.ends_on) > ?', [self::BACKDATE_GRACE_DAYS]);

        return $this->grouped($q, 'backdated_entry', 'e.id');
    }

    private function negativeBalances(): Collection
    {
        $q = DB::table('erp_journal_lines as l')->join('erp_journal_entries as e', 'e.id', '=', 'l.entry_id')->join('erp_accounts as a', 'a.id', '=', 'l.account_id')
            ->whereIn('e.status', ['posted', 'reversed'])->whereIn('a.role_key', [...self::CASH_ROLES, 'inventory'])->where('a.normal_balance', 'debit')
            ->groupBy('a.id', 'a.role_key', 'a.code')->havingRaw('SUM(l.debit) - SUM(l.credit) < -0.005')->selectRaw('a.role_key as role, a.code as code');
        $out = collect();
        $g = $q->get();
        foreach ([['negative_cash', fn ($r) => $r->role !== 'inventory'], ['negative_inventory', fn ($r) => $r->role === 'inventory']] as [$code, $f]) {
            if (($sel = $g->filter($f))->isNotEmpty()) {
                $out->push($this->row($code, $sel->count(), null, $sel->pluck('code')->implode(', ')));
            }
        }

        return $out;
    }

    private function negativeStock(): Collection
    {
        $q = DB::table('stock_levels as s')->where('s.on_hand', '<', 0);

        return $this->grouped($q, 'negative_stock', 's.id')->map(fn ($r) => ['entry_id' => null] + $r);
    }

    /** Receivable/payable control accounts: the maintained balance must equal the sum of their posted lines (party ledgers are built from the same lines). */
    private function controlMismatch(): Collection
    {
        $q = DB::table('erp_journal_lines as l')->join('erp_journal_entries as e', 'e.id', '=', 'l.entry_id')->join('erp_accounts as a', 'a.id', '=', 'l.account_id')
            ->whereIn('e.status', ['posted', 'reversed'])->whereIn('a.role_key', ['receivable', 'payable', 'customer_advances', 'supplier_advances'])
            ->groupBy('a.id', 'a.code', 'a.normal_balance', 'a.current_balance')->selectRaw('a.code as code, a.normal_balance as nb, a.current_balance as cb, SUM(l.debit) as dr, SUM(l.credit) as cr');
        $out = collect();
        $bad = $q->get()->filter(function ($r) {
            $exp = $r->nb === 'debit' ? bcsub((string) $r->dr, (string) $r->cr, 4) : bcsub((string) $r->cr, (string) $r->dr, 4);

            return bccomp(ltrim(bcsub($exp, (string) $r->cb, 4), '-'), '0.01', 4) > 0;
        });
        if ($bad->isNotEmpty()) {
            $out->push($this->row('control_mismatch', $bad->count(), null, $bad->pluck('code')->implode(', ')));
        }

        return $out;
    }

    /** Sales-status orders (confirmed..delivered) without a journal entry, only when the ledger was already live when the order was created (pre-ledger history belongs to the Phase 9 backfill). */
    private function ordersWithoutJournal(): Collection
    {
        // recognised per the revenue_recognition policy (default shipped); a recognition entry that was reversed by hand counts as missing (ERP-007)
        $pol = "COALESCE((SELECT JSON_UNQUOTE(s.value) FROM erp_settings s WHERE s.`key` = 'revenue_recognition'), 'shipped')";
        $q = DB::table('orders as o')->crossJoin(DB::raw('(SELECT MIN(created_at) as first_at FROM erp_journal_entries) as f'))
            ->whereRaw("(o.status = 'delivered' OR (o.status = 'shipped' AND $pol <> 'delivered') OR (o.status IN ('confirmed','processing') AND $pol = 'confirmed'))")
            ->whereColumn('o.created_at', '>=', 'f.first_at')
            ->whereNotExists(fn ($x) => $x->select(DB::raw(1))->from('erp_journal_entries as j')->where('j.source_type', 'order')->whereColumn('j.source_id', 'o.id')
                ->where('j.status', 'posted')->where('j.idempotency_key', 'like', 'order:%:confirm%'));

        return $this->grouped($q, 'order_without_journal', 'o.id')->map(fn ($r) => ['entry_id' => null] + $r);
    }

    private function largeManual(): Collection
    {
        $q = DB::table('erp_journal_entries as e')->whereIn('e.type', ['manual', 'adjusting'])->whereIn('e.status', ['posted', 'reversed'])->where('e.total', '>=', self::largeManualThreshold());

        return $this->grouped($q, 'large_manual', 'e.id');
    }

    private function reopened(): Collection
    {
        $q = DB::table('erp_audit_logs as a')->whereIn('a.action', ['period.reopened', 'year.reopened'])->where('a.created_at', '>=', now()->subDays(90));

        return $this->grouped($q, 'period_reopened', 'a.id')->map(fn ($r) => ['entry_id' => null] + $r);
    }

    /** Ledger net profit vs the legacy Net Profit report (all time). Expected small divergences (depreciation, manual revenue, see ERP_PLAN) make this "low". Opt-in (slow). */
    private function profitMismatch(): Collection
    {
        if (! DB::table('erp_journal_entries')->whereIn('status', ['posted', 'reversed'])->exists()) {
            return collect();
        }
        $np = app(ReportService::class)->netProfitFigures(new ReportFilter('2000-01-01', now()->toDateString()));
        $pl = app(\App\Services\Erp\Reports\LedgerReports::class)->profitLoss('2000-01-01', now()->toDateString());
        $diff = bcsub($pl['net_profit'], (string) $np['net'], 4);

        return bccomp(ltrim($diff, '-'), '0.01', 4) > 0 ? collect([$this->row('profit_mismatch', 1, null, $diff)]) : collect();
    }
}
