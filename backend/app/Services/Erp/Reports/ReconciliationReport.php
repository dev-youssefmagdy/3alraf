<?php

namespace App\Services\Erp\Reports;

use App\DTOs\Reports\ReportFilter;
use App\Enums\OrderStatus;
use App\Models\Erp\Account;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\PurchaseOrder;
use App\Services\Erp\Posting\RevenueRecognition;
use App\Services\Reports\ReportService;

/**
 * Ledger vs business records. Each check: expected (business side) / actual (ledger side) / difference / status (ok when |diff| <= 0.01).
 * Known, documented divergences (see ERP_PLAN phase 5/7) show up here by design instead of being hidden.
 */
class ReconciliationReport extends ReportBase
{
    public const TOLERANCE = '0.01';

    public function __construct(private LedgerReports $ledger, private InventoryReport $inventory, private ReportService $legacy) {}

    private function row(string $key, string $expected, string $actual): array
    {
        $diff = bcsub($actual, $expected, self::S);
        $ok = bccomp(ltrim($diff, '-'), self::TOLERANCE, self::S) <= 0;

        return ['_key' => $key, 'check' => __('erp_rep.rc.'.$key), 'expected' => bcadd($expected, '0', self::S), 'actual' => bcadd($actual, '0', self::S), 'difference' => $diff,
            'status' => $ok ? __('erp_rep.ok') : __('erp_rep.mismatch'), '_ok' => $ok, 'note' => __('erp_rep.rcn.'.$key)];
    }

    public function run(ErpReportFilter $f): array
    {
        $lf = new ReportFilter($f->from, $f->to, false, current_tenant() ? [current_tenant()->id] : null, $f->branchId);
        $np = $this->legacy->netProfitFigures($lf);
        $pl = $this->ledger->profitLoss($f->from, $f->to, $f->branchId);
        $role = fn (string $r) => collect($pl['groups']['revenue'])->first(fn ($x) => $x['account']->role_key === $r)['amount'] ?? '0';
        $bal = $this->ledger->balancesByRole($f->to, $f->branchId);
        $rows = [];

        // 1-3 period: orders / Net Profit report vs ledger
        // A fully returned order (status returned) is dropped by the legacy report but stays in the ledger (kept shipping/fee lines, returns as contra-revenue):
        // its ledger contribution is added to the expected side so the check stays meaningful for every other document (documented benign difference).
        $retEntries = fn () => $this->noClosing($this->lines($f))->where(fn ($w) => $w
            ->where(fn ($x) => $x->where('e.source_type', 'order')->whereIn('e.source_id', Order::where('status', 'returned')->select('id')))
            ->orWhere(fn ($x) => $x->where('e.source_type', 'order_return')->whereIn('e.source_id', OrderReturn::whereHas('order', fn ($o) => $o->where('status', 'returned'))->select('id'))));
        $retRev = (string) ($retEntries()->where('a.role_key', 'sales_revenue')->selectRaw('COALESCE(SUM(erp_journal_lines.credit - erp_journal_lines.debit),0) as n')->value('n') ?? '0');
        $retNet = (string) ($retEntries()->whereIn('a.type', ['revenue', 'expense'])->selectRaw('COALESCE(SUM(erp_journal_lines.credit - erp_journal_lines.debit),0) as n')->value('n') ?? '0');
        // Timing (ERP-011): the Net Profit report counts every admin-confirmed order; the ledger recognises an order at the tenant's recognition
        // point (default: shipment). Confirmed orders not yet recognised are taken out of the expected side (documented, not a mismatch).
        $pending = Order::query()->whereIn('status', RevenueRecognition::pendingValues())->whereBetween('created_at', [$f->from.' 00:00:00', $f->to.' 23:59:59'])
            ->when($f->branchId, fn ($q) => $q->where('branch_id', $f->branchId))->get();
        $pRev = $pending->reduce(fn ($c, $o) => bcadd($c, $o->revenueGoods(), self::S), '0');
        $pCogs = $pending->reduce(fn ($c, $o) => bcadd($c, (string) $o->cogs, self::S), '0');
        $pNet = $pending->reduce(fn ($c, $o) => bcadd($c, (string) $o->net_profit, self::S), '0');
        // Online gateway fees are booked when the money arrives (the provider keeps them): fees of orders outside the recognised set are ledger-only.
        $feeOnly = (string) ($this->noClosing($this->lines($f))->where('e.source_type', 'order')->where('e.idempotency_key', 'like', 'order:%:gateway-fee%')
            ->whereNotIn('e.source_id', Order::query()->whereIn('status', [...RevenueRecognition::values(), OrderStatus::RETURNED->value])->select('id'))
            ->whereIn('a.type', ['revenue', 'expense'])->selectRaw('COALESCE(SUM(erp_journal_lines.credit - erp_journal_lines.debit),0) as n')->value('n') ?? '0');
        $rows[] = $this->row('revenue', bcsub(bcadd((string) $np['revenue'], $retRev, self::S), $pRev, self::S), (string) $role('sales_revenue'));
        $cogsLedger = collect($pl['groups']['cogs'])->first(fn ($x) => $x['account']->role_key === 'cogs')['amount'] ?? '0';
        $rows[] = $this->row('cogs', bcsub(bcsub((string) $np['cogs'], (string) $np['returned_cogs'], self::S), $pCogs, self::S), (string) $cogsLedger);
        // Ledger-only P&L items the legacy report never counted (documented benign differences): stock adjustments/damage/loss,
        // purchase shipping/other costs, depreciation and disposals.
        $ledgerOnly = (string) ($this->noClosing($this->lines($f))->whereIn('a.type', ['revenue', 'expense'])->whereIn('e.source_type', ['stock_adjustment', 'purchase_order', 'fixed_asset'])
            ->selectRaw('COALESCE(SUM(erp_journal_lines.credit - erp_journal_lines.debit),0) as n')->value('n') ?? '0');
        $rows[] = $this->row('net_profit', bcadd(bcsub(bcadd(bcadd((string) $np['net'], $retNet, self::S), $ledgerOnly, self::S), $pNet, self::S), $feeOnly, self::S), $pl['net_profit']);

        // 4 receivable control vs orders due (sales-status orders: total - paid + refunded, less returned goods)
        $sales = [...array_map(fn ($s) => $s->value, OrderStatus::salesStatuses()), OrderStatus::RETURNED->value]; // returned orders stay on the ledger (their returns reduce the receivable)
        $oq = Order::query()->whereIn('status', $sales)->where('created_at', '<=', $f->to.' 23:59:59')->when($f->branchId, fn ($q) => $q->where('branch_id', $f->branchId));
        $due = (string) ($oq->clone()->selectRaw('COALESCE(SUM(total - paid_amount + refunded_amount),0) as d')->value('d') ?? '0');
        // not yet recognised: no receivable was raised, only their prepayments sit on the receivable as a customer credit
        $due = bcsub($due, (string) ($oq->clone()->whereIn('status', RevenueRecognition::pendingValues())->sum('total') ?: '0'), self::S);
        $ret = (string) (OrderReturn::query()->whereIn('status', ['approved', 'refunded'])->whereHas('order', fn ($q) => $q->whereIn('status', $sales)->when($f->branchId, fn ($w) => $w->where('branch_id', $f->branchId)))->sum('return_value') ?: '0');
        $rows[] = $this->row('receivable', bcsub($due, $ret, self::S), bcsub($bal['receivable'] ?? '0', $bal['customer_advances'] ?? '0', self::S));

        // 5 payable control vs purchases still due: received POs + non-PO movements on the payable account
        $billed = (string) (PurchaseOrder::query()->where('status', 'received')->where('received_at', '<=', $f->to.' 23:59:59')->when($f->branchId, fn ($q) => $q->where('branch_id', $f->branchId))->sum('total_cost') ?: '0');
        $ids = Account::where('role_key', 'payable')->pluck('id')->all();
        $nonPo = $ids ? $this->lines(new ErpReportFilter($f->from, $f->to, $f->branchId), false, false)->whereIn('a.id', $ids)->where('e.source_type', '!=', 'purchase_order')
            ->where(fn ($w) => $w->whereNull('e.idempotency_key')->orWhere('e.idempotency_key', 'not like', 'opening:%')) // erp:backfill opening payable: the PO bills already count in $billed
            ->selectRaw('COALESCE(SUM(erp_journal_lines.credit - erp_journal_lines.debit),0) as n')->value('n') : '0';
        $rows[] = $this->row('payable', bcadd($billed, (string) $nonPo, self::S), $bal['payable'] ?? '0');

        // 6 inventory vs stock valuation
        $inv = $this->inventory->figures($f->to, $f->branchId);
        $rows[] = $this->row('inventory', $inv['expected'], $inv['ledger']);

        // 7 carrier COD receivable vs carrier settlement report (delivered, not yet settled)
        $cod = (string) (Order::query()->whereIn('status', ['delivered', 'returned'])->whereNotNull('delivered_at')->whereNull('carrier_settled_at')->where('cod_amount', '>', 0)->where('created_at', '<=', $f->to.' 23:59:59')
            ->when($f->branchId, fn ($q) => $q->where('branch_id', $f->branchId))->sum('cod_amount') ?: '0');
        $rows[] = $this->row('carrier', $cod, $bal['carrier_receivable'] ?? '0');

        $bad = count(array_filter($rows, fn ($r) => ! $r['_ok']));
        $statuses = [];
        foreach ($rows as &$r) {
            $statuses[$r['_key']] = $r['_ok'];
            unset($r['_ok'], $r['_key']);
        }
        unset($r);

        return $this->out('reconciliation', [$this->table([$this->col('check'), $this->col('expected', 'money'), $this->col('actual', 'money'), $this->col('difference', 'money'), $this->col('status'), $this->col('note')], $rows)],
            [$this->card('mismatches', (string) $bad, 'int')], [], ['mismatches' => $bad, 'ok' => $statuses]);
    }
}
