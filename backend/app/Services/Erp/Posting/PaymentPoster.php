<?php

namespace App\Services\Erp\Posting;

use App\Enums\PaymentMethodType;
use App\Models\Erp\Account;
use App\Models\Erp\JournalEntry;
use App\Models\Erp\JournalLine;
use App\Models\Order;
use App\Services\Erp\Backfill\OpeningState;
use App\Services\Payments\RefundService;
use App\Support\Money;

/**
 * Money received / returned for orders. Payments always credit Receivable (a prepayment before recognition shows as a customer credit,
 * cleared when the recognition entry debits the receivable). The key carries the cumulative paid amount so a replay of the same state is a no-op
 * and a retried webhook can never double post.
 *
 *  online -> Gateway balance, wallet -> E-wallet, COD prepaid-shipping leg -> by its method, COD cash collected on delivery -> Carrier COD receivable,
 *  POS counter sale -> POS cash. Online gateway fee: Dr Gateway fees / Cr Gateway when the money arrives (kept by the provider even on cancel).
 *  Refunds credit the account the money is paid back FROM (provider part -> gateway, rest -> chosen/default account), never the account the sale was
 *  collected into (ERP-001/010).
 */
class PaymentPoster extends Poster
{
    /** Post the not-yet-booked part of orders.paid_amount. */
    public function sync(Order $o): ?JournalEntry
    {
        $paid = Money::round((string) $o->paid_amount);
        $this->ensureChart();
        $this->gatewayFee($o);
        $posted = Money::round((string) JournalLine::whereHas('entry', fn ($e) => $e->where('source_type', 'order')->where('source_id', $o->id)->where('status', 'posted')
            ->where('idempotency_key', 'like', 'order:'.$o->id.':pay:%'))
            ->where('account_id', Account::where('role_key', 'receivable')->value('id'))->sum('credit')); // a manually reversed payment is not "booked"
        $base = OpeningState::baseline($o)['paid']; // already inside the opening balances
        $posted = Money::add($posted, $base);
        $delta = Money::sub($paid, $posted);
        if (Money::cmp($delta, '0') <= 0) {
            return null;
        }
        $b = $o->branch_id;
        $debits = [];
        if ($o->counter_sale) { // POS: the customer paid at the counter, cash is in the register
            $debits['pos_cash'] = $delta;
        } elseif ($o->prepay_method_id) { // COD with prepaid shipping: that leg goes through the prepay method, the rest is carrier cash
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

    /** Online gateway fee, booked once when money arrives through the gateway (ERP-023: not part of the recognition entry, never reversed on cancel). */
    public function gatewayFee(Order $o): ?JournalEntry
    {
        if ($o->paymentMethod?->type !== PaymentMethodType::ONLINE || ! $this->pos($o->paid_amount) || ! $this->pos($o->payment_fee) || OpeningState::absorbed($o)) {
            return null;
        }
        if (Money::cmp(Money::round($this->netDebit('order', $o->id, 'gateway_fees')), '0') !== 0) {
            return null; // already booked (also by a legacy recognition entry)
        }

        return $this->post('order:'.$o->id.':gateway-fee', $this->today(), 'Gateway fee for order '.$o->number, 'order', $o->id,
            [$this->dr('gateway_fees', $o->payment_fee, $o->branch_id, $o->number), $this->cr('gateway', $o->payment_fee, $o->branch_id, $o->number)], $o->branch_id);
    }

    /** Cancel with money already collected: the refund leg (Dr Receivable / Cr account the money is paid back from). Key carries the refunded total. */
    public function cancelRefund(Order $o): ?JournalEntry
    {
        $refunded = Money::round((string) $o->refunded_amount);
        $amount = Money::sub($refunded, OpeningState::baseline($o)['refunded']);
        if (! $this->pos($amount)) {
            return null;
        }
        $provider = app(RefundService::class)->onlinePortion($o, $amount, true);
        $lines = [$this->dr('receivable', $amount, $o->branch_id, $o->number, 'customer', $o->customer_id)];
        foreach (self::refundCredits($o, $amount, $provider, $o->prepay_method_id ? Money::min($amount, (string) $o->prepaid_shipping) : '0') as $role => $amt) {
            $lines[] = $this->cr($role, $amt, $o->branch_id, $o->number);
        }

        return $this->post('order:'.$o->id.':cancel-refund:'.$refunded, $this->today(), 'Refund of cancelled order '.$o->number, 'order', $o->id, $lines, $o->branch_id);
    }

    /**
     * Carrier settled a delivered COD order: Dr Cash|Bank (cod collected net of carrier fee) + Shipping payable (carrier fee) / Cr Carrier receivable.
     * $into = account the carrier paid into (ERP-024). A carrier fee above the COD amount stays in shipping payable (paid with "Pay carrier").
     */
    public function carrierSettled(Order $o, string $into = 'cash'): ?JournalEntry
    {
        $cod = Money::round((string) $o->cod_amount);
        if (! $this->pos($cod)) {
            return null;
        }
        $into = in_array($into, ['cash', 'bank', 'pos_cash', 'wallet', 'gateway'], true) ? $into : 'cash';
        $fee = Money::min((string) $o->shipping_cost, $cod);
        $net = Money::sub($cod, $fee);
        $lines = [$this->dr($into, $net, $o->branch_id, $o->number), $this->dr('shipping_payable', $fee, $o->branch_id, $o->number), $this->cr('carrier_receivable', $cod, $o->branch_id, $o->number)];

        return $this->post('order:'.$o->id.':carrier-settle', $this->today(), 'Carrier settlement for order '.$o->number, 'order', $o->id, $lines, $o->branch_id);
    }
}
