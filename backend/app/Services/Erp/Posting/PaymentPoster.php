<?php

namespace App\Services\Erp\Posting;

use App\Models\Erp\JournalEntry;
use App\Models\Order;
use App\Support\Money;

/**
 * Money received / returned for orders. Payments always credit Receivable (a prepayment before confirmation shows as a customer credit,
 * cleared when the confirmation debits the receivable). The key carries the cumulative paid amount so a replay of the same state is a no-op
 * and a retried webhook can never double post.
 *
 *  online -> Gateway balance, wallet -> E-wallet, COD prepaid-shipping leg -> by its method, COD cash collected on delivery -> Carrier COD receivable.
 */
class PaymentPoster extends Poster
{
    /** Post the not-yet-booked part of orders.paid_amount. */
    public function sync(Order $o): ?JournalEntry
    {
        $paid = Money::round((string) $o->paid_amount);
        $this->ensureChart();
        $posted = Money::round((string) \App\Models\Erp\JournalLine::whereHas('entry', fn ($e) => $e->where('source_type', 'order')->where('source_id', $o->id)->where('idempotency_key', 'like', 'order:'.$o->id.':pay:%'))
            ->where('account_id', \App\Models\Erp\Account::where('role_key', 'receivable')->value('id'))->sum('credit'));
        $base = \App\Services\Erp\Backfill\OpeningState::baseline($o)['paid']; // already inside the opening balances
        $posted = Money::add($posted, $base);
        $delta = Money::sub($paid, $posted);
        if (Money::cmp($delta, '0') <= 0) {
            return null;
        }
        $b = $o->branch_id;
        $debits = [];
        if ($o->prepay_method_id) { // COD with prepaid shipping: that leg goes through the prepay method, the rest is carrier cash
            $pre = (string) $o->prepaid_shipping;
            $leg = Money::sub(Money::min($paid, $pre), Money::min($posted, $pre));
            $debits[self::settlementRole($o->prepayMethod)] = $leg;
            $debits['carrier_receivable'] = Money::add($debits['carrier_receivable'] ?? '0', Money::sub($delta, $leg));
        } else {
            $debits[self::settlementRole($o->paymentMethod, (bool) $o->shipping_company_id)] = $delta;
        }
        $lines = [];
        foreach ($debits as $role => $amt) {
            $lines[] = $this->dr($role, $amt, $b, $o->number);
        }
        $lines[] = $this->cr('receivable', $delta, $b, $o->number, 'customer', $o->customer_id);

        return $this->post('order:'.$o->id.':pay:'.$paid, $this->today(), 'Payment for order '.$o->number, 'order', $o->id, $lines, $b);
    }

    /** Cancel with money already collected: the refund cash leg (Dr Receivable / Cr cash-like account). Key carries the refunded total. */
    public function cancelRefund(Order $o): ?JournalEntry
    {
        $refunded = Money::round((string) $o->refunded_amount);
        $amount = Money::sub($refunded, \App\Services\Erp\Backfill\OpeningState::baseline($o)['refunded']);
        if (! $this->pos($amount)) {
            return null;
        }
        $role = self::settlementRole($o->payingMethod(), (bool) $o->shipping_company_id);
        $lines = [$this->dr('receivable', $amount, $o->branch_id, $o->number, 'customer', $o->customer_id), $this->cr($role, $amount, $o->branch_id, $o->number)];

        return $this->post('order:'.$o->id.':cancel-refund:'.$refunded, $this->today(), 'Refund of cancelled order '.$o->number, 'order', $o->id, $lines, $o->branch_id);
    }

    /** Carrier settled a delivered COD order: Dr Cash (cod collected net of carrier fee) + Shipping payable (carrier fee) / Cr Carrier receivable. */
    public function carrierSettled(Order $o): ?JournalEntry
    {
        $cod = Money::round((string) $o->cod_amount);
        if (! $this->pos($cod)) {
            return null;
        }
        $fee = Money::min((string) $o->shipping_cost, $cod);
        $net = Money::sub($cod, $fee);
        $lines = [$this->dr('cash', $net, $o->branch_id, $o->number), $this->dr('shipping_payable', $fee, $o->branch_id, $o->number), $this->cr('carrier_receivable', $cod, $o->branch_id, $o->number)];

        return $this->post('order:'.$o->id.':carrier-settle', $this->today(), 'Carrier settlement for order '.$o->number, 'order', $o->id, $lines, $o->branch_id);
    }
}
