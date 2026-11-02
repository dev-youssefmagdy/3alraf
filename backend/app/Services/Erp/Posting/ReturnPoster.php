<?php

namespace App\Services\Erp\Posting;

use App\Models\Erp\JournalEntry;
use App\Models\OrderReturn;
use App\Support\Money;

/**
 * Returns & refunds. A return is CONTRA-REVENUE (Sales returns), never an expense.
 *
 *  approved : Dr Sales returns (net of VAT) + VAT payable / Cr Receivable ;
 *             restocked : Dr Inventory / Cr COGS ; damaged (restock=false) : Dr Inventory loss / Cr COGS ;
 *             return shipping cost : Dr Shipping expense / Cr Shipping payable ; commission follows the order (delta entry)
 *  refunded : Dr Receivable (+ Shipping revenue for a refunded shipping part) / Cr cash-like account of the paying method
 * Fully-returned orders keep their shipping/fee lines (those costs and income were real); see docs/ERP_PLAN.md Phase 4 notes.
 */
class ReturnPoster extends Poster
{
    public function __construct(\App\Services\Erp\Accounting\JournalService $j, \App\Services\Erp\Accounting\CoaTemplate $c, private SalesPoster $sales)
    {
        parent::__construct($j, $c);
    }

    /** Cumulative VAT of this return: round(tax x (P+v)/B) - round(tax x P/B), P = value of the order's earlier approved/refunded returns. */
    private function vat(OrderReturn $r): array
    {
        $o = $r->order;
        $prior = Money::round((string) OrderReturn::where('order_id', $r->order_id)->where('id', '<', $r->id)->whereIn('status', ['approved', 'refunded'])->sum('return_value'));
        $v = (string) $r->return_value;

        return ['vat' => $o->returnTax($v, $prior), 'net' => $o->returnNetOfTax($v, $prior), 'inclusive' => $o->taxIncluded()];
    }

    public function approved(OrderReturn $r): ?JournalEntry
    {
        $r->loadMissing('order');
        $o = $r->order;
        $b = $r->branch_id ?? $o->branch_id;
        $x = $this->vat($r);
        $rv = (string) $r->return_value;
        $receivable = $x['inclusive'] ? $rv : Money::add($rv, $x['vat']);
        $m = $r->number ?: 'RET-'.$r->id;

        $lines = [
            $this->dr('sales_returns', $x['net'], $b, $m), $this->dr('vat_payable', $x['vat'], $b, $m),
            $this->cr('receivable', $receivable, $b, $m, 'customer', $o->customer_id),
            $this->dr($r->restock ? 'inventory' : 'inventory_loss', $r->returned_cogs, $b, $m), $this->cr('cogs', $r->returned_cogs, $b, $m),
        ];
        $entry = $this->post('return:'.$r->id.':goods', $this->today(), 'Return '.$m, 'order_return', $r->id, $lines, $b);

        if ($this->pos($r->return_shipping_cost)) {
            $this->post('return:'.$r->id.':ship', $this->today(), 'Return shipping '.$m, 'order_return', $r->id,
                [$this->dr('shipping_expense', $r->return_shipping_cost, $b, $m), $this->cr('shipping_payable', $r->return_shipping_cost, $b, $m)], $b);
        }
        $this->sales->syncCommission($o->refresh(), 'ret'.$r->id);

        return $entry;
    }

    public function refunded(OrderReturn $r): ?JournalEntry
    {
        $r->loadMissing('order');
        $o = $r->order;
        $amount = Money::round((string) $r->refund_amount);
        if (! $this->pos($amount)) {
            return null;
        }
        $x = $this->vat($r);
        $cap = $x['inclusive'] ? (string) $r->return_value : Money::add((string) $r->return_value, $x['vat']);
        $shipping = Money::min(Money::max('0', Money::sub($amount, $cap)), (string) $o->shipping_net); // refunded shipping reverses shipping revenue
        $b = $r->branch_id ?? $o->branch_id;
        $m = $r->number ?: 'RET-'.$r->id;
        $role = self::settlementRole($o->payingMethod(), (bool) $o->shipping_company_id);
        $lines = [
            $this->dr('receivable', Money::sub($amount, $shipping), $b, $m, 'customer', $o->customer_id),
            $this->dr('shipping_revenue', $shipping, $b, $m),
            $this->cr($role, $amount, $b, $m),
        ];

        return $this->post('return:'.$r->id.':refund', $this->today(), 'Refund of return '.$m, 'order_return', $r->id, $lines, $b);
    }
}
