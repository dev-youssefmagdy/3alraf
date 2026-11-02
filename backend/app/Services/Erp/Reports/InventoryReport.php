<?php

namespace App\Services\Erp\Reports;

use App\Models\Branch;
use App\Models\Erp\Account;
use App\Models\Erp\JournalLine;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;

/** Inventory valuation: ledger inventory balance per branch, compared with the StockLedger valuation (sum of on_hand x avg_cost). */
class InventoryReport extends ReportBase
{
    /** @return array{rows:array,ledger:string,stock:string,transit:string,difference:string} keyed rows by branch id (0 = unallocated) */
    public function figures(?string $to, ?int $branchId = null): array
    {
        $ids = Account::whereIn('role_key', ['inventory', 'inventory_transit'])->pluck('id', 'role_key');
        $led = JournalLine::query()->join('erp_journal_entries as e', 'e.id', '=', 'erp_journal_lines.entry_id')->whereIn('e.status', ['posted', 'reversed'])
            ->when($to, fn ($q) => $q->where('e.entry_date', '<=', $to))->when($branchId, fn ($q) => $q->where('erp_journal_lines.branch_id', $branchId))
            ->whereIn('erp_journal_lines.account_id', $ids->values()->all() ?: [0])->groupBy('erp_journal_lines.branch_id', 'erp_journal_lines.account_id')
            ->selectRaw('erp_journal_lines.branch_id as b, erp_journal_lines.account_id as acc, SUM(erp_journal_lines.debit - erp_journal_lines.credit) as net')->get();
        $stock = StockLevel::query()->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->groupBy('branch_id')->selectRaw('branch_id as b, SUM(ROUND(on_hand * avg_cost, 2)) as v')->pluck('v', 'b');
        // COGS already booked at confirmation for orders whose stock is still on hand (deducted only at shipping): ledger is legitimately lower by that amount
        $committed = DB::table('orders')->join('erp_journal_entries as e', fn ($j) => $j->on('e.idempotency_key', '=', DB::raw("CONCAT('order:', orders.id, ':confirm')")))
            ->whereIn('orders.status', ['confirmed', 'processing'])->where('orders.stock_deducted', false)->where('e.status', 'posted')
            ->when($branchId, fn ($q) => $q->where('orders.branch_id', $branchId))->groupBy('orders.branch_id')->selectRaw('orders.branch_id as b, SUM(orders.cogs + orders.packaging_cost) as v')->pluck('v', 'b');
        $rows = [];
        $get = function (int $b) use (&$rows) {
            return $rows[$b] ??= ['ledger' => '0', 'transit' => '0', 'stock' => '0', 'committed' => '0'];
        };
        foreach ($committed as $b => $v) {
            $r = $get((int) $b);
            $r['committed'] = bcadd((string) $v, '0', self::S);
            $rows[(int) $b] = $r;
        }
        foreach ($led as $l) {
            $r = $get((int) $l->b);
            $k = $l->acc == ($ids['inventory'] ?? 0) ? 'ledger' : 'transit';
            $r[$k] = bcadd($r[$k], (string) $l->net, self::S);
            $rows[(int) $l->b] = $r;
        }
        foreach ($stock as $b => $v) {
            $r = $get((int) $b);
            $r['stock'] = bcadd((string) $v, '0', self::S);
            $rows[(int) $b] = $r;
        }
        foreach ($rows as &$r) {
            $r['expected'] = bcsub($r['stock'], $r['committed'], self::S);
            $r['difference'] = bcsub($r['ledger'], $r['expected'], self::S);
        }
        unset($r);
        ksort($rows);
        $t = ['ledger' => '0', 'transit' => '0', 'stock' => '0', 'committed' => '0', 'expected' => '0', 'difference' => '0'];
        foreach ($rows as $r) {
            foreach ($t as $k => $_) {
                $t[$k] = bcadd($t[$k], $r[$k], self::S);
            }
        }

        return ['rows' => $rows] + $t;
    }

    public function run(ErpReportFilter $f): array
    {
        $fig = $this->figures($f->to, $f->branchId);
        $br = $this->branchNames();
        $out = [];
        foreach ($fig['rows'] as $b => $r) {
            $out[] = ['branch' => $b ? ($br[$b] ?? '#'.$b) : __('erp_rep.unallocated'), 'ledger' => $r['ledger'], 'transit' => $r['transit'], 'stock' => $r['stock'], 'difference' => $r['difference'],
                'status' => bccomp(ltrim($r['difference'], '-'), '0.01', self::S) <= 0 ? __('erp_rep.ok') : __('erp_rep.mismatch')];
        }
        $out[] = ['branch' => __('erp.f.total'), 'ledger' => $fig['ledger'], 'transit' => $fig['transit'], 'stock' => $fig['stock'], 'difference' => $fig['difference'], '_total' => true,
            'status' => bccomp(ltrim($fig['difference'], '-'), '0.01', self::S) <= 0 ? __('erp_rep.ok') : __('erp_rep.mismatch')];
        $notes = [__('erp_rep.inv_note')];
        if (bccomp($fig['committed'], '0', self::S) !== 0) {
            $notes[] = __('erp_rep.inv_committed', ['amount' => number_format((float) $fig['committed'], 2)]);
        }
        if ($f->to < now()->toDateString()) {
            $notes[] = __('erp_rep.inv_snapshot');
        }

        return $this->out('inventory-valuation', [$this->table([$this->col('branch'), $this->col('ledger', 'money'), $this->col('transit', 'money'), $this->col('stock', 'money'), $this->col('difference', 'money'), $this->col('status')], $out)],
            [$this->card('ledger', $fig['ledger']), $this->card('stock', $fig['stock']), $this->card('difference', $fig['difference'])], $notes, $fig);
    }
}
