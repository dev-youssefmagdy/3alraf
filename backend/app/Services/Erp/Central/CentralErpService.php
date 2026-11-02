<?php

namespace App\Services\Erp\Central;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\Erp\Reports\PartyLedger;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cross-tenant READ-ONLY aggregates. Every query groups by tenant_id; figures of different tenants are never added together here
 * (consolidation, per currency, is done in groups()). Money is a bcmath string, scale 4.
 */
class CentralErpService
{
    private const S = 4;

    public const SORTS = ['name', 'revenue', 'expenses', 'net', 'cash', 'ar', 'ap', 'inventory', 'equity', 'last_posting', 'anomalies'];

    public function __construct(private AnomalyService $anomalies, private ExchangeRates $rates) {}

    private function lines()
    {
        return DB::table('erp_journal_lines as l')->join('erp_journal_entries as e', 'e.id', '=', 'l.entry_id')->join('erp_accounts as a', 'a.id', '=', 'l.account_id')
            ->whereIn('e.status', ['posted', 'reversed']);
    }

    /**
     * One row per tenant. $from/$to bound the P&L figures (revenue/expenses/net); balances (cash, AR, AP, inventory, equity) are as of $to.
     *
     * @param  array{q?:?string,currency?:?string,period?:?string,anomalies?:?string,sort?:?string,dir?:?string}  $o
     * @return Collection<int,array>
     */
    public function overview(?string $from = null, ?string $to = null, array $o = []): Collection
    {
        $tenants = Tenant::query()->orderBy('name')->get();
        $ids = $tenants->pluck('id')->all();

        // balances as of $to: per tenant x account type x role
        $bal = $this->lines()->when($to, fn ($q) => $q->where('e.entry_date', '<=', $to))->groupBy('e.tenant_id', 'a.type', 'a.role_key')
            ->selectRaw('e.tenant_id as t, a.type as ty, a.role_key as role, SUM(l.debit) as dr, SUM(l.credit) as cr')->get()->groupBy('t');
        // P&L in the window, year-end closing entries (and their reversals) excluded like the tenant P&L
        $pl = $this->lines()->whereIn('a.type', ['revenue', 'expense'])->where('e.type', '!=', 'closing')
            ->whereNotExists(fn ($x) => $x->select(DB::raw(1))->from('erp_journal_entries as o')->whereColumn('o.id', 'e.reverses_id')->where('o.type', 'closing'))
            ->when($from, fn ($q) => $q->where('e.entry_date', '>=', $from))->when($to, fn ($q) => $q->where('e.entry_date', '<=', $to))
            ->groupBy('e.tenant_id', 'a.type')->selectRaw('e.tenant_id as t, a.type as ty, SUM(l.debit) as dr, SUM(l.credit) as cr')->get()->groupBy('t');
        $last = DB::table('erp_journal_entries')->whereIn('status', ['posted', 'reversed'])->groupBy('tenant_id')->selectRaw('tenant_id as t, MAX(entry_date) as d, COUNT(*) as n')->get()->keyBy('t');
        $per = DB::table('erp_periods')->groupBy('tenant_id', 'status')->selectRaw('tenant_id as t, status as s, COUNT(*) as n, MAX(ends_on) as last_end')->get()->groupBy('t');
        $counts = $this->anomalies->countsByTenant($ids);

        $rows = $tenants->map(function (Tenant $t) use ($bal, $pl, $last, $per, $counts) {
            $b = $bal[$t->id] ?? collect();
            $sum = fn ($rows, $debitSide) => $rows->reduce(fn ($c, $r) => bcadd($c, $debitSide ? bcsub((string) $r->dr, (string) $r->cr, self::S) : bcsub((string) $r->cr, (string) $r->dr, self::S), self::S), '0.0000');
            $role = fn (array $roles, bool $dr) => $sum($b->filter(fn ($r) => in_array($r->role, $roles, true)), $dr);
            $p = $pl[$t->id] ?? collect();
            $revenue = $sum($p->where('ty', 'revenue'), false);
            $expenses = $sum($p->where('ty', 'expense'), true);
            // equity = equity accounts + residual earnings (all revenue/expense balances incl. closing entries)
            $earn = bcsub($sum($b->where('ty', 'revenue'), false), $sum($b->where('ty', 'expense'), true), self::S);
            $periods = $per[$t->id] ?? collect();
            $open = (int) ($periods->firstWhere('s', 'open')->n ?? 0);
            $closed = (int) ($periods->firstWhere('s', 'closed')->n ?? 0);

            return [
                'tenant' => $t, 'id' => $t->id, 'name' => (string) $t->name, 'currency' => (string) $t->currency_code,
                'revenue' => $revenue, 'expenses' => $expenses, 'net' => bcsub($revenue, $expenses, self::S),
                'cash' => $role(AnomalyService::CASH_ROLES, true), 'ar' => $role(['receivable'], true), 'ap' => $role(['payable'], false), 'inventory' => $role(['inventory'], true),
                'equity' => bcadd($sum($b->where('ty', 'equity'), false), $earn, self::S),
                'last_posting' => $last[$t->id]->d ?? null, 'entries' => (int) ($last[$t->id]->n ?? 0), 'periods_open' => $open, 'periods_closed' => $closed,
                'last_closed' => $periods->firstWhere('s', 'closed')->last_end ?? null,
                'anomalies' => (int) ($counts[$t->id] ?? 0), 'has_erp' => isset($last[$t->id]) || $periods->isNotEmpty(),
            ];
        });

        return $this->filterSort($rows, $o)->values();
    }

    private function filterSort(Collection $rows, array $o): Collection
    {
        $rows = $rows->when(! empty($o['q']), fn ($c) => $c->filter(fn ($r) => stripos($r['name'], (string) $o['q']) !== false || stripos($r['tenant']->slug, (string) $o['q']) !== false))
            ->when(! empty($o['currency']), fn ($c) => $c->where('currency', strtoupper((string) $o['currency'])))
            ->when(($o['period'] ?? '') === 'open', fn ($c) => $c->filter(fn ($r) => $r['periods_open'] > 0))
            ->when(($o['period'] ?? '') === 'closed', fn ($c) => $c->filter(fn ($r) => $r['periods_open'] === 0 && $r['periods_closed'] > 0))
            ->when(($o['anomalies'] ?? '') === 'yes', fn ($c) => $c->filter(fn ($r) => $r['anomalies'] > 0));
        $sort = in_array($o['sort'] ?? '', self::SORTS, true) ? $o['sort'] : 'name';
        $desc = ($o['dir'] ?? 'asc') === 'desc';
        $key = fn ($r) => in_array($sort, ['name'], true) ? strtolower($r['name']) : ($sort === 'last_posting' ? (string) $r['last_posting'] : (float) $r[$sort]);

        return $desc ? $rows->sortByDesc($key) : $rows->sortBy($key);
    }

    /**
     * Per-currency groups of the overview rows: totals are sums of tenants of the SAME currency only.
     * With $convertTo, each tenant's figures are ALSO shown converted with the stored rate (tenant override, else global); a tenant without a stored rate is listed in 'unconverted'.
     *
     * @return Collection<string,array{currency:string,tenants:Collection,totals:array}>
     */
    public function groups(Collection $overview, ?string $convertTo = null): Collection
    {
        $keys = ['revenue', 'expenses', 'net', 'cash', 'ar', 'ap', 'inventory', 'equity'];
        $convertTo = $convertTo ? strtoupper($convertTo) : null;
        $this->rates->flush();

        return $overview->filter(fn ($r) => $r['has_erp'])->groupBy('currency')->map(function (Collection $rows, string $cur) use ($keys, $convertTo) {
            $tot = array_fill_keys($keys, '0.0000');
            foreach ($rows as $r) {
                foreach ($keys as $k) {
                    $tot[$k] = bcadd($tot[$k], $r[$k], self::S);
                }
            }
            $g = ['currency' => $cur, 'tenants' => $rows->values(), 'totals' => $tot, 'converted' => null];
            if ($convertTo && $convertTo !== $cur) {
                $conv = array_fill_keys($keys, '0.0000');
                $used = [];
                $missing = [];
                foreach ($rows as $r) {
                    $rate = $this->rates->find($cur, $convertTo, $r['id']);
                    if (! $rate) {
                        $missing[] = $r['name'];

                        continue;
                    }
                    $used[$r['id']] = $rate;
                    foreach ($keys as $k) {
                        $conv[$k] = bcadd($conv[$k], $this->rates->convert($r[$k], $rate['rate']), self::S);
                    }
                }
                $g['converted'] = ['to' => $convertTo, 'totals' => $conv, 'rates' => $used, 'missing' => $missing, 'complete' => ! $missing];
            }

            return $g;
        })->sortKeys();
    }

    /** Customer/supplier outstanding per tenant + top parties + totals. @return Collection<int,array> keyed by tenant id */
    public function parties(?int $tenantId = null, int $top = 5): Collection
    {
        $rows = $this->lines()->whereIn('l.party_type', ['customer', 'supplier'])->whereNotNull('l.party_id')
            ->whereIn('a.role_key', ['receivable', 'customer_advances', 'payable', 'supplier_advances'])->when($tenantId, fn ($q) => $q->where('e.tenant_id', $tenantId))
            ->groupBy('e.tenant_id', 'l.party_type', 'l.party_id')
            ->selectRaw("e.tenant_id as t, l.party_type as pt, l.party_id as pid, SUM(CASE WHEN l.party_type = 'customer' THEN l.debit - l.credit ELSE l.credit - l.debit END) as bal")->get();
        $names = [
            'customer' => Customer::withoutGlobalScopes()->withTrashed()->whereIn('id', $rows->where('pt', 'customer')->pluck('pid'))->pluck('name', 'id'),
            'supplier' => Supplier::withoutGlobalScopes()->withTrashed()->whereIn('id', $rows->where('pt', 'supplier')->pluck('pid'))->pluck('name', 'id'),
        ];
        $tenants = Tenant::query()->when($tenantId, fn ($q) => $q->whereKey($tenantId))->orderBy('name')->get();

        return $tenants->mapWithKeys(function (Tenant $t) use ($rows, $names, $top) {
            $out = ['tenant' => $t, 'currency' => (string) $t->currency_code];
            foreach (['customer' => 'ar', 'supplier' => 'ap'] as $type => $k) {
                $mine = $rows->where('t', $t->id)->where('pt', $type)->map(fn ($r) => ['id' => (int) $r->pid, 'name' => (string) ($names[$type][$r->pid] ?? '#'.$r->pid), 'balance' => bcadd((string) $r->bal, '0', self::S)]);
                $pos = $mine->filter(fn ($r) => bccomp($r['balance'], '0', self::S) > 0)->sortByDesc(fn ($r) => (float) $r['balance'])->values();
                $out[$k] = ['total' => $pos->reduce(fn ($c, $r) => bcadd($c, $r['balance'], self::S), '0.0000'), 'parties' => $pos->count(), 'top' => $pos->take($top)->all(),
                    'credit' => $mine->filter(fn ($r) => bccomp($r['balance'], '0', self::S) < 0)->reduce(fn ($c, $r) => bcadd($c, $r['balance'], self::S), '0.0000')];
            }

            return [$t->id => $out];
        });
    }

    /** Ageing summary (buckets) per tenant for customers and suppliers, via the tenant's own PartyLedger. Only tenants that have party lines (bounded by $tenantIds). */
    public function ageing(array $tenantIds): array
    {
        $out = [];
        foreach (Tenant::whereIn('id', $tenantIds ?: [0])->get() as $t) {
            $out[$t->id] = TenantContext::run($t, fn () => [
                'ar' => app(PartyLedger::class)->ageing('customer')['totals'], 'ap' => app(PartyLedger::class)->ageing('supplier')['totals'],
            ]);
        }

        return $out;
    }

    /** Ledger inventory vs stock valuation per tenant (one grouped query each). @return Collection<int,array> */
    public function inventory(): Collection
    {
        $led = $this->lines()->whereIn('a.role_key', ['inventory', 'inventory_transit'])->groupBy('e.tenant_id', 'a.role_key')
            ->selectRaw('e.tenant_id as t, a.role_key as role, SUM(l.debit - l.credit) as net')->get()->groupBy('t');
        $stock = DB::table('stock_levels')->groupBy('tenant_id')->selectRaw('tenant_id as t, SUM(ROUND(on_hand * avg_cost, 2)) as v, SUM(on_hand) as units')->get()->keyBy('t');

        return Tenant::query()->orderBy('name')->get()->map(function (Tenant $t) use ($led, $stock) {
            $l = $led[$t->id] ?? collect();
            $ledger = bcadd((string) ($l->firstWhere('role', 'inventory')->net ?? '0'), '0', self::S);
            $transit = bcadd((string) ($l->firstWhere('role', 'inventory_transit')->net ?? '0'), '0', self::S);
            $val = bcadd((string) ($stock[$t->id]->v ?? '0'), '0', self::S);
            $diff = bcsub($ledger, $val, self::S);

            return ['tenant' => $t, 'id' => $t->id, 'name' => $t->name, 'currency' => $t->currency_code, 'ledger' => $ledger, 'transit' => $transit, 'stock' => $val, 'units' => (int) ($stock[$t->id]->units ?? 0),
                'difference' => $diff, 'ok' => bccomp(ltrim($diff, '-'), '0.01', self::S) <= 0];
        });
    }

    /** Periods per tenant: counts, last closed, and the latest reopen events from erp_audit_logs. */
    public function periods(?int $tenantId = null): array
    {
        $periods = DB::table('erp_periods as p')->join('erp_fiscal_years as y', 'y.id', '=', 'p.fiscal_year_id')->when($tenantId, fn ($q) => $q->where('p.tenant_id', $tenantId))
            ->orderBy('p.tenant_id')->orderByDesc('p.starts_on')->select('p.*', 'y.name as year_name', 'y.status as year_status')->get()->groupBy('tenant_id');
        $reopen = DB::table('erp_audit_logs')->whereIn('action', ['period.reopened', 'year.reopened'])->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->orderByDesc('id')->limit(100)->get();

        return ['periods' => $periods, 'reopen' => $reopen, 'tenants' => Tenant::query()->when($tenantId, fn ($q) => $q->whereKey($tenantId))->orderBy('name')->get()];
    }

    /** Read-only normalized statements of ONE tenant (its own scope + currency). */
    public function statements(Tenant $tenant, string $from, string $to): array
    {
        return TenantContext::run($tenant, function () use ($from, $to) {
            $cat = app(\App\Services\Erp\Reports\ReportCatalog::class);
            $f = new \App\Services\Erp\Reports\ErpReportFilter($from, $to);

            return ['trial-balance' => $cat->run('trial-balance', $f, null), 'profit-loss' => $cat->run('profit-loss', $f, null), 'balance-sheet' => $cat->run('balance-sheet', $f, null)];
        });
    }

}
