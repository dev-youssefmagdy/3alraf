<?php

namespace App\Services\Erp\Central;

use App\DTOs\Reports\ReportFilter;
use App\Models\Tenant;
use App\Services\Reports\ReportService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared by the tenant ERP hub (one tenant) and the central monitor (all tenants). Every check is ONE grouped SQL query keyed by tenant_id,
 * except the optional "deep" net-profit comparison which needs the legacy report per tenant.
 * Row: tenant_id, code, severity (high|medium|low), count, entry_id (a sample journal entry or null), detail.
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

    /** @param ?array<int,int> $tenantIds null = every tenant @return Collection<int,array> */
    public function detect(?array $tenantIds = null, bool $deep = false): Collection
    {
        $rows = collect()
            ->merge($this->unbalanced($tenantIds))->merge($this->oldDrafts($tenantIds))->merge($this->postingFailed($tenantIds))
            ->merge($this->backdated($tenantIds))->merge($this->negativeBalances($tenantIds))->merge($this->negativeStock($tenantIds))
            ->merge($this->controlMismatch($tenantIds))->merge($this->ordersWithoutJournal($tenantIds))->merge($this->largeManual($tenantIds))
            ->merge($this->reopened($tenantIds));
        if ($deep) {
            $rows = $rows->merge($this->profitMismatch($tenantIds));
        }
        $order = ['high' => 0, 'medium' => 1, 'low' => 2];

        return $rows->sortBy(fn ($r) => [$order[$r['severity']], $r['tenant_id'], $r['code']])->values();
    }

    /** @return array<int,int> total anomaly count per tenant id */
    public function countsByTenant(?array $tenantIds = null, bool $deep = false): array
    {
        return $this->detect($tenantIds, $deep)->groupBy('tenant_id')->map(fn ($g) => (int) $g->sum('count'))->all();
    }

    private function row(int $tenant, string $code, int $count, ?int $entry = null, ?string $detail = null): array
    {
        return ['tenant_id' => $tenant, 'code' => $code, 'severity' => self::severityOf($code), 'count' => $count, 'entry_id' => $entry, 'detail' => $detail];
    }

    private function scoped($q, string $col, ?array $ids)
    {
        return $ids === null ? $q : $q->whereIn($col, $ids ?: [0]);
    }

    private function grouped($q, string $tenantCol, string $code, string $idCol): Collection
    {
        return $q->groupBy($tenantCol)->selectRaw("$tenantCol as t, COUNT(*) as c, MIN($idCol) as sample")->get()
            ->map(fn ($r) => $this->row((int) $r->t, $code, (int) $r->c, $r->sample ? (int) $r->sample : null));
    }

    private function unbalanced(?array $ids): Collection
    {
        $q = DB::table('erp_journal_lines as l')->join('erp_journal_entries as e', 'e.id', '=', 'l.entry_id')->whereIn('e.status', ['posted', 'reversed'])
            ->groupBy('e.tenant_id', 'e.id')->havingRaw('ROUND(SUM(l.debit) - SUM(l.credit), 4) <> 0')->selectRaw('e.tenant_id as t, e.id as id');

        return $this->scoped($q, 'e.tenant_id', $ids)->get()->groupBy('t')->map(fn ($g, $t) => $this->row((int) $t, 'unbalanced_entry', $g->count(), (int) $g->min('id')))->values();
    }

    private function oldDrafts(?array $ids): Collection
    {
        $q = DB::table('erp_journal_entries as e')->where('e.status', 'draft')->where('e.created_at', '<', now()->subDays(self::DRAFT_DAYS));

        return $this->grouped($this->scoped($q, 'e.tenant_id', $ids), 'e.tenant_id', 'old_draft', 'e.id');
    }

    private function postingFailed(?array $ids): Collection
    {
        $q = DB::table('erp_audit_logs as a')->where('a.action', 'posting.failed')->where('a.created_at', '>=', now()->subDays(30));

        return $this->grouped($this->scoped($q, 'a.tenant_id', $ids), 'a.tenant_id', 'posting_failed', 'a.id')->map(fn ($r) => ['entry_id' => null] + $r);
    }

    /** Posted more than a few days after the end of the period it landed in (period was open after the fact or entry re-dated). */
    private function backdated(?array $ids): Collection
    {
        $q = DB::table('erp_journal_entries as e')->join('erp_periods as p', 'p.id', '=', 'e.period_id')->whereIn('e.status', ['posted', 'reversed'])
            ->where('e.type', '!=', 'closing')->whereNotNull('e.posted_at')
            ->whereRaw('DATEDIFF(DATE(e.posted_at), p.ends_on) > ?', [self::BACKDATE_GRACE_DAYS]);

        return $this->grouped($this->scoped($q, 'e.tenant_id', $ids), 'e.tenant_id', 'backdated_entry', 'e.id');
    }

    private function negativeBalances(?array $ids): Collection
    {
        $q = DB::table('erp_journal_lines as l')->join('erp_journal_entries as e', 'e.id', '=', 'l.entry_id')->join('erp_accounts as a', 'a.id', '=', 'l.account_id')
            ->whereIn('e.status', ['posted', 'reversed'])->whereIn('a.role_key', [...self::CASH_ROLES, 'inventory'])->where('a.normal_balance', 'debit')
            ->groupBy('a.tenant_id', 'a.id', 'a.role_key', 'a.code')->havingRaw('SUM(l.debit) - SUM(l.credit) < -0.005')->selectRaw('a.tenant_id as t, a.role_key as role, a.code as code');
        $out = collect();
        foreach ($this->scoped($q, 'a.tenant_id', $ids)->get()->groupBy('t') as $t => $g) {
            foreach ([['negative_cash', fn ($r) => $r->role !== 'inventory'], ['negative_inventory', fn ($r) => $r->role === 'inventory']] as [$code, $f]) {
                if (($sel = $g->filter($f))->isNotEmpty()) {
                    $out->push($this->row((int) $t, $code, $sel->count(), null, $sel->pluck('code')->implode(', ')));
                }
            }
        }

        return $out;
    }

    private function negativeStock(?array $ids): Collection
    {
        $q = DB::table('stock_levels as s')->where('s.on_hand', '<', 0);

        return $this->grouped($this->scoped($q, 's.tenant_id', $ids), 's.tenant_id', 'negative_stock', 's.id')->map(fn ($r) => ['entry_id' => null] + $r);
    }

    /** Receivable/payable control accounts: the maintained balance must equal the sum of their posted lines (party ledgers are built from the same lines). */
    private function controlMismatch(?array $ids): Collection
    {
        $q = DB::table('erp_journal_lines as l')->join('erp_journal_entries as e', 'e.id', '=', 'l.entry_id')->join('erp_accounts as a', 'a.id', '=', 'l.account_id')
            ->whereIn('e.status', ['posted', 'reversed'])->whereIn('a.role_key', ['receivable', 'payable', 'customer_advances', 'supplier_advances'])
            ->groupBy('a.tenant_id', 'a.id', 'a.code', 'a.normal_balance', 'a.current_balance')->selectRaw('a.tenant_id as t, a.code as code, a.normal_balance as nb, a.current_balance as cb, SUM(l.debit) as dr, SUM(l.credit) as cr');
        $out = collect();
        foreach ($this->scoped($q, 'a.tenant_id', $ids)->get()->groupBy('t') as $t => $g) {
            $bad = $g->filter(function ($r) {
                $exp = $r->nb === 'debit' ? bcsub((string) $r->dr, (string) $r->cr, 4) : bcsub((string) $r->cr, (string) $r->dr, 4);

                return bccomp(ltrim(bcsub($exp, (string) $r->cb, 4), '-'), '0.01', 4) > 0;
            });
            if ($bad->isNotEmpty()) {
                $out->push($this->row((int) $t, 'control_mismatch', $bad->count(), null, $bad->pluck('code')->implode(', ')));
            }
        }

        return $out;
    }

    /** Sales-status orders (confirmed..delivered) without a journal entry, only for tenants whose ledger was already live when the order was created (pre-ledger history belongs to the Phase 9 backfill). */
    private function ordersWithoutJournal(?array $ids): Collection
    {
        $q = DB::table('orders as o')->join(DB::raw('(SELECT tenant_id, MIN(created_at) as first_at FROM erp_journal_entries GROUP BY tenant_id) as f'), 'f.tenant_id', '=', 'o.tenant_id')
            ->whereIn('o.status', array_map(fn ($s) => $s->value, \App\Enums\OrderStatus::salesStatuses()))->whereColumn('o.created_at', '>=', 'f.first_at')
            ->whereNotExists(fn ($x) => $x->select(DB::raw(1))->from('erp_journal_entries as j')->whereColumn('j.tenant_id', 'o.tenant_id')->where('j.source_type', 'order')->whereColumn('j.source_id', 'o.id'));

        return $this->grouped($this->scoped($q, 'o.tenant_id', $ids), 'o.tenant_id', 'order_without_journal', 'o.id')->map(fn ($r) => ['entry_id' => null] + $r);
    }

    private function largeManual(?array $ids): Collection
    {
        $q = DB::table('erp_journal_entries as e')->whereIn('e.type', ['manual', 'adjusting'])->whereIn('e.status', ['posted', 'reversed'])->where('e.total', '>=', self::largeManualThreshold());

        return $this->grouped($this->scoped($q, 'e.tenant_id', $ids), 'e.tenant_id', 'large_manual', 'e.id');
    }

    private function reopened(?array $ids): Collection
    {
        $q = DB::table('erp_audit_logs as a')->whereIn('a.action', ['period.reopened', 'year.reopened'])->where('a.created_at', '>=', now()->subDays(90));

        return $this->grouped($this->scoped($q, 'a.tenant_id', $ids), 'a.tenant_id', 'period_reopened', 'a.id')->map(fn ($r) => ['entry_id' => null] + $r);
    }

    /** Ledger net profit vs the legacy Net Profit report (all time). Expected small divergences (depreciation, manual revenue, see ERP_PLAN) make this "low". Per-tenant, so opt-in. */
    private function profitMismatch(?array $ids): Collection
    {
        $with = DB::table('erp_journal_entries')->whereIn('status', ['posted', 'reversed'])->when($ids !== null, fn ($q) => $q->whereIn('tenant_id', $ids ?: [0]))->distinct()->pluck('tenant_id');
        $out = collect();
        foreach (Tenant::whereIn('id', $with)->get() as $tenant) {
            $diff = TenantContext::run($tenant, function () use ($tenant) {
                $np = app(ReportService::class)->netProfitFigures(new ReportFilter('2000-01-01', now()->toDateString(), false, [$tenant->id]));
                $pl = app(\App\Services\Erp\Reports\LedgerReports::class)->profitLoss('2000-01-01', now()->toDateString());

                return bcsub($pl['net_profit'], (string) $np['net'], 4);
            });
            if (bccomp(ltrim($diff, '-'), '0.01', 4) > 0) {
                $out->push($this->row($tenant->id, 'profit_mismatch', 1, null, $diff));
            }
        }

        return $out;
    }
}
