<?php

namespace App\Services\Erp\Posting;

use App\Enums\CommissionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Models\AffiliateCommission;
use App\Models\Erp\JournalEntry;
use App\Models\Order;
use App\Services\Erp\Backfill\OpeningState;
use App\Support\Money;

/**
 * Orders -> ledger. Revenue, VAT and COGS are recognised ONCE, at the recognition point (RevenueRecognition: shipped by default,
 * delivered, or legacy confirmed). All amounts are read from the order (CALCULATIONS.md); COGS is `orders.cogs`, which OrderStatusService
 * re-values at shipment to the cost the stock ledger actually removed (sale_out), so ledger inventory == stock valuation.
 *
 *  recognition (key order:{id}:confirm - historical name) :
 *            Dr Receivable (total) / Cr Sales revenue (goods net of discount & inclusive VAT), Shipping revenue, VAT payable,
 *            Fee income (charged fee + COD fee), Other revenue (packaging net of VAT)
 *            Dr COGS / Cr Inventory (or Goods out for delivery when it left stock earlier) ; Dr Shipping expense / Cr Shipping payable ;
 *            Dr Gateway fees / Cr fee account (non-online methods; online gateway fees are posted at payment) ;
 *            Dr Affiliate expense / Cr Affiliate payable ; Dr Packaging / Cr Packaging supplies
 *  shipment : policy delivered -> Dr Goods out for delivery / Cr Inventory (order:{id}:ship);
 *             already recognised (legacy confirmed) -> COGS true-up to the shipped cost (order:{id}:cogs-trueup)
 *  cancel (before shipment, or returned-to-sender after it): the recognition family is reversed; never recognised -> nothing.
 */
class SalesPoster extends Poster
{
    public static function confirmKey(Order $o): string
    {
        return 'order:'.$o->id.':confirm';
    }

    public static function shipKey(Order $o): string
    {
        return 'order:'.$o->id.':ship';
    }

    public static function trueUpKey(Order $o): string
    {
        return 'order:'.$o->id.':cogs-trueup';
    }

    /** @param bool $assumeConfirmed backfill of a fully returned order (status returned): it WAS recognised, revenue must exist */
    public function confirmed(Order $o, bool $assumeConfirmed = false): ?JournalEntry
    {
        if (! RevenueRecognition::recognised($o) && ! ($assumeConfirmed && $o->status === OrderStatus::RETURNED)) {
            return null;
        }
        if (OpeningState::absorbed($o)) {
            return null; // recognised before the opening date: carried by the opening balances (erp:backfill), never recognised twice
        }
        $b = $o->branch_id;
        $customer = $o->customer_id;
        $rev = $o->revenueGoods();
        $vat = Money::add((string) $o->tax, (string) $o->packaging_tax);
        $fees = Money::add((string) $o->payment_fee_charged, (string) $o->cod_fee);
        $pkg = $o->packagingRevenue();
        $receivable = Money::add($rev, (string) $o->shipping_net, $vat, $fees, $pkg);
        $online = $o->paymentMethod?->type === PaymentMethodType::ONLINE; // the provider keeps its fee even on cancel: posted with the payment
        $cogsFrom = $this->live(self::shipKey($o)) ? 'goods_in_delivery' : 'inventory';
        $m = $o->number;

        $lines = [
            $this->dr('receivable', $receivable, $b, $m, 'customer', $customer),
            $this->cr('sales_revenue', $rev, $b, $m),
            $this->cr('shipping_revenue', $o->shipping_net, $b, $m),
            $this->cr('vat_payable', $vat, $b, $m),
            $this->cr('fee_income', $fees, $b, $m),
            $this->cr('other_revenue', $pkg, $b, $m),
            // costs, same figures as OrderProfitService
            $this->dr('cogs', $o->cogs, $b, $m), $this->cr($cogsFrom, $o->cogs, $b, $m),
            $this->dr('shipping_expense', $o->shipping_cost, $b, $m), $this->cr('shipping_payable', $o->shipping_cost, $b, $m),
            $this->dr('affiliate_expense', $o->affiliate_commission, $b, $m), $this->cr('affiliate_payable', $o->affiliate_commission, $b, $m),
            $this->dr('packaging', $o->packaging_cost, $b, $m), $this->cr('packaging_supplies', $o->packaging_cost, $b, $m), // packaging is not a stock item (ERP-008)
        ];
        if (! $online) {
            $lines[] = $this->dr('gateway_fees', $o->payment_fee, $b, $m);
            $lines[] = $this->cr(self::feeCreditRole($o->paymentMethod), $o->payment_fee, $b, $m);
        }

        return $this->post(self::confirmKey($o), $this->today(), 'Order '.$o->number.' revenue recognised', 'order', $o->id, $lines, $b);
    }

    /** Stock left the warehouse (sale_out posted by the stock ledger; orders.cogs already holds the shipped cost). */
    public function shipped(Order $o): ?JournalEntry
    {
        if (! $o->stock_deducted || OpeningState::absorbed($o)) {
            return null;
        }
        $b = $o->branch_id;
        $m = $o->number;
        if ($this->live(self::confirmKey($o))) { // recognised before shipment (legacy policy): align COGS with what actually left stock
            $delta = Money::sub(Money::round((string) $o->cogs), Money::round($this->netDebit('order', $o->id, 'cogs')));
            if (Money::cmp($delta, '0') === 0) {
                return null;
            }
            $abs = ltrim($delta, '-');
            $lines = Money::cmp($delta, '0') > 0
                ? [$this->dr('cogs', $abs, $b, $m), $this->cr('inventory', $abs, $b, $m)]
                : [$this->dr('inventory', $abs, $b, $m), $this->cr('cogs', $abs, $b, $m)];

            return $this->post($this->deltaKey(self::trueUpKey($o)), $this->today(), 'Order '.$o->number.' COGS at shipped cost', 'order', $o->id, $lines, $b);
        }
        if (RevenueRecognition::policy() === 'delivered' && $o->status === OrderStatus::SHIPPED) {
            return $this->post(self::shipKey($o), $this->today(), 'Order '.$o->number.' out for delivery', 'order', $o->id,
                [$this->dr('goods_in_delivery', $o->cogs, $b, $m), $this->cr('inventory', $o->cogs, $b, $m)], $b);
        }

        return null;
    }

    /** Cancel (also returned-to-sender after shipment): reverse the recognition family if (and only if) it was posted. */
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

        return $this->reverseLive('order', $o->id, [self::confirmKey($o), 'order:'.$o->id.':commission:', self::shipKey($o), self::trueUpKey($o)], 'order cancelled');
    }

    /**
     * Commission follows orders.affiliate_commission (reduced by partial returns, 0 on full return): post only the delta.
     * A commission already PAID to the affiliate is never reversed (CALCULATIONS §Affiliate commission: paid commissions are never altered, ERP-004).
     */
    public function syncCommission(Order $o, string $tag): ?JournalEntry
    {
        if (! $this->live(self::confirmKey($o))) {
            return null; // not recognised (or already reversed): nothing to adjust
        }
        $target = (string) $o->affiliate_commission;
        $row = AffiliateCommission::where('order_id', $o->id)->first();
        if ($row && $row->status === CommissionStatus::PAID) {
            $target = Money::max($target, (string) $row->amount);
        }
        $delta = Money::sub($target, Money::round($this->netDebit('order', $o->id, 'affiliate_expense')));
        if (Money::cmp($delta, '0') === 0) {
            return null;
        }
        $abs = ltrim($delta, '-');
        $lines = Money::cmp($delta, '0') > 0
            ? [$this->dr('affiliate_expense', $abs, $o->branch_id, $o->number), $this->cr('affiliate_payable', $abs, $o->branch_id, $o->number)]
            : [$this->dr('affiliate_payable', $abs, $o->branch_id, $o->number), $this->cr('affiliate_expense', $abs, $o->branch_id, $o->number)];

        return $this->post($this->deltaKey('order:'.$o->id.':commission:'.$tag), $this->today(), 'Order '.$o->number.' commission adjustment', 'order', $o->id, $lines, $o->branch_id);
    }

    /**
     * Key for a DELTA posting (amount = target - already booked): the first one uses $base, a later non-zero delta (after a repost or a
     * second adjustment with the same tag) gets a counter. Replays are safe because a replayed delta is zero and posts nothing.
     */
    private function deltaKey(string $base): string
    {
        $n = JournalEntry::where('idempotency_key', $base)->orWhere('idempotency_key', 'like', str_replace(['%', '_'], ['\\%', '\\_'], $base).':%')->count();

        return $n ? $base.':'.($n + 1) : $base;
    }
}
