<?php

namespace App\Services\Erp\Posting;

use App\Enums\OrderStatus;
use App\Models\Erp\JournalEntry;
use App\Models\Order;
use App\Services\Erp\Backfill\OpeningState;
use App\Support\Money;

/**
 * Orders -> ledger. Revenue is recognised ONCE, when an admin confirms (status confirmed/processing/shipped/delivered) - the same
 * rule as the profit report - so ledger net profit == the Net Profit report. All amounts are read from the order (CALCULATIONS.md).
 *
 *  confirm : Dr Receivable (total) / Cr Sales revenue (goods net of discount & inclusive VAT), Shipping revenue, VAT payable,
 *            Fee income (charged fee + COD fee), Other revenue (packaging net of VAT)
 *            Dr COGS / Cr Inventory ; Dr Shipping expense / Cr Shipping payable ; Dr Gateway fees / Cr settlement account ;
 *            Dr Affiliate expense / Cr Affiliate payable ; Dr Packaging / Cr Inventory
 *  cancel after confirmation : the confirm entry (+ later commission adjustments) is reversed; before confirmation : nothing.
 */
class SalesPoster extends Poster
{
    public static function confirmKey(Order $o): string
    {
        return 'order:'.$o->id.':confirm';
    }

    /** @param bool $assumeConfirmed backfill of a fully returned order (status returned): it WAS confirmed, revenue must exist */
    public function confirmed(Order $o, bool $assumeConfirmed = false): ?JournalEntry
    {
        if (! in_array($o->status, OrderStatus::salesStatuses(), true) && ! ($assumeConfirmed && $o->status === OrderStatus::RETURNED)) {
            return null;
        }
        if (OpeningState::absorbed($o)) {
            return null; // confirmed before the opening date: carried by the opening balances (erp:backfill), never recognised twice
        }
        $b = $o->branch_id;
        $customer = $o->customer_id;
        $rev = $o->revenueGoods();
        $vat = Money::add((string) $o->tax, (string) $o->packaging_tax);
        $fees = Money::add((string) $o->payment_fee_charged, (string) $o->cod_fee);
        $pkg = $o->packagingRevenue();
        $receivable = Money::add($rev, (string) $o->shipping_net, $vat, $fees, $pkg);
        $settle = self::settlementRole($o->paymentMethod);
        $m = $o->number;

        $lines = [
            $this->dr('receivable', $receivable, $b, $m, 'customer', $customer),
            $this->cr('sales_revenue', $rev, $b, $m),
            $this->cr('shipping_revenue', $o->shipping_net, $b, $m),
            $this->cr('vat_payable', $vat, $b, $m),
            $this->cr('fee_income', $fees, $b, $m),
            $this->cr('other_revenue', $pkg, $b, $m),
            // costs, same figures as OrderProfitService
            $this->dr('cogs', $o->cogs, $b, $m), $this->cr('inventory', $o->cogs, $b, $m),
            $this->dr('shipping_expense', $o->shipping_cost, $b, $m), $this->cr('shipping_payable', $o->shipping_cost, $b, $m),
            $this->dr('gateway_fees', $o->payment_fee, $b, $m), $this->cr($settle, $o->payment_fee, $b, $m),
            $this->dr('affiliate_expense', $o->affiliate_commission, $b, $m), $this->cr('affiliate_payable', $o->affiliate_commission, $b, $m),
            $this->dr('packaging', $o->packaging_cost, $b, $m), $this->cr('inventory', $o->packaging_cost, $b, $m),
        ];

        return $this->post(self::confirmKey($o), $this->today(), 'Order '.$o->number.' confirmed', 'order', $o->id, $lines, $b);
    }

    /** Cancel: reverse the confirmation family if (and only if) the order had been confirmed. */
    public function cancelled(Order $o): int
    {
        if (OpeningState::absorbed($o)) { // its receivable sits in the opening balances: write the still-open part off against opening equity
            $b = OpeningState::baseline($o);
            $open = Money::add(Money::sub((string) $o->total, $b['paid']), $b['refunded']);
            if ($this->pos($open)) {
                $this->post('order:'.$o->id.':opening-cancel', $this->today(), 'Order '.$o->number.' cancelled (opening balance)', 'order', $o->id,
                    [$this->dr('opening_equity', $open, $o->branch_id, $o->number), $this->cr('receivable', $open, $o->branch_id, $o->number, 'customer', $o->customer_id)], $o->branch_id);
            }

            return 0;
        }

        return $this->reverseLive('order', $o->id, ['order:'.$o->id.':confirm', 'order:'.$o->id.':commission:'], 'order cancelled');
    }

    /** Commission follows orders.affiliate_commission (reduced by partial returns, 0 on full return): post only the delta. */
    public function syncCommission(Order $o, string $tag): ?JournalEntry
    {
        if (! JournalEntry::where('idempotency_key', self::confirmKey($o))->where('status', 'posted')->exists()) {
            return null; // not confirmed (or already reversed): nothing to adjust
        }
        $delta = Money::sub((string) $o->affiliate_commission, Money::round($this->netDebit('order', $o->id, 'affiliate_expense')));
        if (Money::cmp($delta, '0') === 0) {
            return null;
        }
        $abs = ltrim($delta, '-');
        $lines = Money::cmp($delta, '0') > 0
            ? [$this->dr('affiliate_expense', $abs, $o->branch_id, $o->number), $this->cr('affiliate_payable', $abs, $o->branch_id, $o->number)]
            : [$this->dr('affiliate_payable', $abs, $o->branch_id, $o->number), $this->cr('affiliate_expense', $abs, $o->branch_id, $o->number)];

        return $this->post('order:'.$o->id.':commission:'.$tag, $this->today(), 'Order '.$o->number.' commission adjustment', 'order', $o->id, $lines, $o->branch_id);
    }
}
