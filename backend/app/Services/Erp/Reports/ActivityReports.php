<?php

namespace App\Services\Erp\Reports;

use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

/** Transaction-level reports (journal entries, sales, purchases, returns, COGS, expenses) - all derived from journal lines. */
class ActivityReports extends ReportBase
{
    public function journalEntries(ErpReportFilter $f, ?int $limit = 1000): array
    {
        $limit = $limit ?: 50000; // export mode (null) is still capped
        $q = $this->lines($f)->select('e.entry_date', 'e.number', 'e.id as entry_id', 'e.type', 'e.status', 'e.source_type', 'e.description', 'erp_journal_lines.branch_id', 'erp_journal_lines.party_type',
            'erp_journal_lines.party_id', 'erp_journal_lines.memo', 'erp_journal_lines.debit', 'erp_journal_lines.credit', 'a.code', 'a.name as account_name')->orderBy('e.entry_date')->orderBy('e.id')->orderBy('erp_journal_lines.id');
        $tot = (clone $this->lines($f))->selectRaw('COALESCE(SUM(erp_journal_lines.debit),0) as dr, COALESCE(SUM(erp_journal_lines.credit),0) as cr, COUNT(DISTINCT e.id) as n')->first();
        $rows = $limit ? $q->limit($limit + 1)->get() : $q->get();
        $truncated = $limit && $rows->count() > $limit;
        $rows = $limit ? $rows->take($limit) : $rows;
        $br = $this->branchNames();
        $out = $rows->map(fn ($r) => [
            'date' => (string) substr((string) $r->entry_date, 0, 10), 'number' => $r->number, 'type' => __('erp.entry_types.'.$r->type), 'source' => (string) $r->source_type,
            'account' => $r->code.' '.$this->accName($r->account_name), 'branch' => $br[$r->branch_id] ?? '', 'party' => $r->party_type ? $r->party_type.' #'.$r->party_id : '',
            'memo' => (string) ($r->memo ?: $r->description), 'debit' => (string) $r->debit, 'credit' => (string) $r->credit,
        ])->all();
        $out[] = ['date' => __('erp.f.total'), 'debit' => (string) $tot->dr, 'credit' => (string) $tot->cr, '_total' => true];

        return $this->out('journal-entries', [$this->table([$this->col('date'), $this->col('number'), $this->col('type'), $this->col('source'), $this->col('account'), $this->col('branch'), $this->col('party'), $this->col('memo'), $this->col('debit', 'money'), $this->col('credit', 'money')], $out)],
            [$this->card('entries', (string) $tot->n, 'int'), $this->card('debit', (string) $tot->dr), $this->card('credit', (string) $tot->cr)],
            $truncated ? [__('erp_rep.truncated', ['n' => $limit])] : [], ['total_debit' => (string) $tot->dr, 'total_credit' => (string) $tot->cr, 'balanced' => bccomp((string) $tot->dr, (string) $tot->cr, self::S) === 0]);
    }

    private function accName(?string $json): string
    {
        $a = json_decode((string) $json, true);

        return is_array($a) ? (string) ($a[app()->getLocale()] ?? $a['en'] ?? reset($a)) : (string) $json;
    }

    /**
     * Per-entry roll-up. $cols: key => [roles, 'cr'|'dr'] (signed in that direction); rows with all-zero columns are dropped.
     *
     * @return array<int,array<string,mixed>>
     */
    private function rollup(ErpReportFilter $f, array $sources, array $cols, string $partyType, string $partyKey): array
    {
        $q = $this->lines($f, true, false)->when($sources, fn ($w) => $w->whereIn('e.source_type', $sources));
        $sel = ['e.id as eid', 'e.entry_date', 'e.number', 'e.type', 'e.source_type', 'e.source_id', 'e.description', 'e.branch_id as ebranch'];
        $zero = [];
        foreach ($cols as $k => [$roles, $dir]) {
            $expr = $dir === 'cr' ? 'erp_journal_lines.credit - erp_journal_lines.debit' : 'erp_journal_lines.debit - erp_journal_lines.credit';
            $sel[] = DB::raw("COALESCE(SUM(CASE WHEN a.role_key IN ({$this->inList($roles)}) THEN $expr ELSE 0 END),0) as $k");
            $zero[] = "ABS($k) > 0";
        }
        $q->select($sel)->groupBy('e.id', 'e.entry_date', 'e.number', 'e.type', 'e.source_type', 'e.source_id', 'e.description', 'e.branch_id')
            ->havingRaw(implode(' OR ', $zero))->orderBy('e.entry_date')->orderBy('e.id');
        $rows = $q->get();
        $ids = $rows->pluck('eid')->all();
        $parties = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (DB::table('erp_journal_lines')->where('tenant_id', $this->tenantId())->whereIn('entry_id', $chunk)->where('party_type', $partyType)->whereNotNull('party_id')->get(['entry_id', 'party_id']) as $p) {
                $parties[$p->entry_id] ??= $p->party_id;
            }
        }
        $names = $partyType === 'customer' ? Customer::withTrashed()->whereIn('id', array_unique($parties))->pluck('name', 'id') : Supplier::withTrashed()->whereIn('id', array_unique($parties))->pluck('name', 'id');
        $orders = Order::withTrashed()->whereIn('id', $rows->where('source_type', 'order')->pluck('source_id')->all())->pluck('number', 'id');
        $br = $this->branchNames();

        return $rows->map(function ($r) use ($cols, $parties, $names, $orders, $br, $partyKey) {
            $o = ['date' => substr((string) $r->entry_date, 0, 10), 'number' => $r->number, 'document' => $r->source_type === 'order' ? (string) ($orders[$r->source_id] ?? '') : (string) $r->description,
                'branch' => $br[$r->ebranch] ?? '', $partyKey => (string) ($names[$parties[$r->eid] ?? 0] ?? ''), 'kind' => $r->type === 'reversal' ? __('erp.entry_types.reversal') : ''];
            foreach (array_keys($cols) as $k) {
                $o[$k] = bcadd((string) $r->$k, '0', self::S);
            }

            return $o;
        })->all();
    }

    private function withTotal(array $rows, array $sumKeys, string $labelKey = 'date'): array
    {
        $t = [$labelKey => __('erp.f.total'), '_total' => true];
        foreach ($sumKeys as $k) {
            $t[$k] = $this->sum($rows, $k);
        }

        return array_merge($rows, [$t]);
    }

    public function sales(ErpReportFilter $f): array
    {
        $cols = ['goods' => [['sales_revenue'], 'cr'], 'shipping' => [['shipping_revenue'], 'cr'], 'fees' => [['fee_income'], 'cr'], 'other' => [['other_revenue'], 'cr'], 'vat' => [['vat_payable'], 'cr']];
        $rows = $this->rollup($f, ['order'], $cols, 'customer', 'customer');
        foreach ($rows as &$r) {
            $r['net_sales'] = bcadd(bcadd(bcadd($r['goods'], $r['shipping'], self::S), $r['fees'], self::S), $r['other'], self::S);
            $r['total'] = bcadd($r['net_sales'], $r['vat'], self::S);
        }
        unset($r);
        $net = $this->sum($rows, 'net_sales');
        $vat = $this->sum($rows, 'vat');
        $rows = $this->withTotal($rows, ['goods', 'shipping', 'fees', 'other', 'net_sales', 'vat', 'total']);
        $c = [$this->col('date'), $this->col('number'), $this->col('document'), $this->col('customer'), $this->col('branch'), $this->col('kind'), $this->col('goods', 'money'), $this->col('shipping', 'money'),
            $this->col('fees', 'money'), $this->col('other', 'money'), $this->col('net_sales', 'money'), $this->col('vat', 'money'), $this->col('total', 'money')];

        return $this->out('sales', [$this->table($c, $rows)], [$this->card('net_sales', $net), $this->card('vat', $vat)], [], ['net_sales' => $net]);
    }

    public function salesReturns(ErpReportFilter $f): array
    {
        $cols = ['returns' => [['sales_returns'], 'dr'], 'vat' => [['vat_payable'], 'dr'], 'cogs_back' => [['cogs', 'inventory_loss'], 'cr'], 'restock' => [['inventory'], 'dr']];
        $rows = $this->rollup($f, ['order_return'], $cols, 'customer', 'customer');
        $rows = $this->withTotal($rows, ['returns', 'vat', 'cogs_back', 'restock']);
        $t = end($rows);
        $c = [$this->col('date'), $this->col('number'), $this->col('document'), $this->col('customer'), $this->col('branch'), $this->col('kind'), $this->col('returns', 'money'), $this->col('vat', 'money'), $this->col('cogs_back', 'money'), $this->col('restock', 'money')];

        return $this->out('sales-returns', [$this->table($c, $rows)], [$this->card('returns', $t['returns'])], [], ['returns' => $t['returns']]);
    }

    public function purchases(ErpReportFilter $f): array
    {
        $cols = ['inventory' => [['inventory'], 'dr'], 'expenses' => [['purchase_expenses'], 'dr'], 'payable' => [['payable'], 'cr']];
        $rows = $this->rollup($f, ['purchase_order'], $cols, 'supplier', 'supplier');
        $rows = array_values(array_filter($rows, fn ($r) => bccomp($r['inventory'], '0', self::S) !== 0 || bccomp($r['expenses'], '0', self::S) !== 0)); // receipts only, not payments
        $rows = $this->withTotal($rows, ['inventory', 'expenses', 'payable']);
        $t = end($rows);
        $c = [$this->col('date'), $this->col('number'), $this->col('document'), $this->col('supplier'), $this->col('branch'), $this->col('kind'), $this->col('inventory', 'money'), $this->col('expenses', 'money'), $this->col('payable', 'money')];

        return $this->out('purchases', [$this->table($c, $rows)], [$this->card('payable', $t['payable'])], [], ['payable' => $t['payable'], 'inventory' => $t['inventory']]);
    }

    public function cogs(ErpReportFilter $f): array
    {
        $cols = ['cogs' => [['cogs'], 'dr'], 'shrinkage' => [['inventory_loss'], 'dr']];
        $rows = $this->rollup($f, [], $cols, 'customer', 'customer');
        foreach ($rows as &$r) {
            $r['total'] = bcadd($r['cogs'], $r['shrinkage'], self::S);
        }
        unset($r);
        $rows = $this->withTotal($rows, ['cogs', 'shrinkage', 'total']);
        $t = end($rows);
        $c = [$this->col('date'), $this->col('number'), $this->col('document'), $this->col('branch'), $this->col('kind'), $this->col('cogs', 'money'), $this->col('shrinkage', 'money'), $this->col('total', 'money')];

        return $this->out('cogs', [$this->table($c, $rows)], [$this->card('cogs', $t['cogs']), $this->card('shrinkage', $t['shrinkage'])], [], ['cogs' => $t['cogs'], 'shrinkage' => $t['shrinkage']]);
    }

    public function expenses(ErpReportFilter $f): array
    {
        $q = $this->noClosing($this->lines($f))->where('a.type', 'expense')->where(fn ($w) => $w->whereNull('a.subtype')->orWhereNotIn('a.subtype', ['cogs', 'shrinkage']))
            ->groupBy('a.id', 'a.code', 'a.name')->select('a.id', 'a.code', 'a.name', DB::raw('COUNT(DISTINCT e.id) as n'), DB::raw('SUM(erp_journal_lines.debit - erp_journal_lines.credit) as amount'))->orderByDesc('amount');
        $cats = ExpenseCategory::query()->whereNotNull('erp_account_id')->get()->groupBy('erp_account_id')->map(fn ($g) => $g->map(fn ($c) => (string) $c->name)->implode(', '));
        $rows = $q->get()->map(fn ($r) => ['code' => $r->code, 'account' => $this->accName($r->name), 'category' => (string) ($cats[$r->id] ?? ''), 'count' => (string) $r->n, 'amount' => bcadd((string) $r->amount, '0', self::S)])
            ->filter(fn ($r) => bccomp($r['amount'], '0', self::S) !== 0)->values()->all();
        $total = $this->sum($rows, 'amount');
        $rows = array_map(fn ($r) => $r + ['share' => bccomp($total, '0', self::S) === 0 ? '0.00' : bcdiv(bcmul($r['amount'], '100', 6), $total, 2)], $rows);
        $rows[] = ['code' => __('erp.f.total'), 'amount' => $total, 'count' => (string) array_sum(array_column($rows, 'count')), '_total' => true];

        return $this->out('expenses', [$this->table([$this->col('code'), $this->col('account'), $this->col('category'), $this->col('count', 'int'), $this->col('amount', 'money'), $this->col('share', 'pct')], $rows)],
            [$this->card('amount', $total)], [], ['total' => $total]);
    }
}
