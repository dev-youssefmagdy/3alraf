<?php

namespace App\Services\Orders;

use App\DTOs\Orders\CalculatorInput;
use App\DTOs\Orders\OrderTotals;
use App\Support\Money;

/**
 * EXACT implementation of docs/CALCULATIONS.md (Order totals + Profit + Affiliate commission).
 * Pure: no DB access. Round half-up at the end of each labeled step only.
 */
class OrderCalculator
{
    public function calculate(CalculatorInput $in): OrderTotals
    {
        // Definitions
        $subtotal = '0';
        $cogs = '0';
        $lineSub = [];
        foreach ($in->lines as $i => $l) {
            $lineSub[$i] = Money::mul($l['unit_price'], $l['qty']);
            $subtotal = Money::add($subtotal, $lineSub[$i]);
            $cogs = Money::add($cogs, Money::mul($l['unit_cost'], $l['qty']));
        }

        // 1. Discount
        [$discount, $lineDiscounts, $freeShipping] = $this->discount($in, $subtotal, $lineSub);
        $afterDiscount = Money::sub($subtotal, $discount);

        // 2. Shipping
        $shipping = '0.00';
        $shippingCost = '0.00';
        if ($in->shipping) {
            $shipping = Money::round($in->shipping['price']);
            $freeOver = $in->shipping['free_over'] ?? null;
            $shippingCost = isset($in->shipping['cost']) && $in->shipping['cost'] !== null ? Money::round($in->shipping['cost']) : $shipping;
            if ($freeOver !== null && $freeOver !== '' && Money::cmp($afterDiscount, $freeOver) >= 0) {
                $shipping = '0.00';
            }
        }
        $shippingDiscount = $freeShipping ? $shipping : '0.00';
        $shippingNet = Money::sub($shipping, $shippingDiscount);

        // 3. Tax (percent rate)
        $rate = $in->taxRate;
        $tax = '0.00';
        $taxAdded = '0.00';
        if (bccomp($rate, '0', 6) > 0) {
            $tax = $in->pricesIncludeTax
                ? Money::round(bcdiv(bcmul($afterDiscount, $rate, 8), bcadd('100', $rate, 8), 8))
                : Money::percent($afterDiscount, $rate);
            $taxAdded = $in->pricesIncludeTax ? '0.00' : $tax;
        }

        // 4. Payment fee on F = subtotal - discount + shipping_net + tax(exclusive)
        $base = Money::add($afterDiscount, $shippingNet, $taxAdded);
        $fee = Money::round(bcadd(bcdiv(bcmul($base, $in->feePercent, 8), '100', 8), $in->feeFixed, 8));
        $feeCharged = $in->feeBearer === 'customer' ? $fee : '0.00';
        $codFee = $this->codExtraFee($in, $base, $afterDiscount);

        // 5/6. Total, due
        $total = Money::add($afterDiscount, $shippingNet, $taxAdded, $feeCharged, $codFee);
        $due = Money::sub($total, $in->paidAmount);

        // COD split: customer prepays shipping_net (online/wallet), the carrier collects the rest
        $prepaid = ($in->isCod && $in->codCollectShippingUpfront) ? $shippingNet : '0.00';
        $codAmount = $in->isCod ? Money::sub($total, $prepaid) : '0.00';

        // revenue_goods = subtotal - discount - tax_in_goods (VAT inside inclusive prices is never revenue)
        $revenueGoods = $in->pricesIncludeTax ? Money::sub($afterDiscount, $tax) : $afterDiscount;

        // Affiliate commission on goods after discount, excl. VAT
        $commission = bccomp($in->affiliatePercent, '0', 6) > 0 ? Money::percent($revenueGoods, $in->affiliatePercent) : '0.00';

        // Profit: (revenue_goods - cogs) + shipping_net - shipping_cost - fee - commission + fee_charged + cod_fee
        $net = Money::sub(Money::add(Money::sub($revenueGoods, $cogs), $shippingNet, $feeCharged, $codFee), Money::add($shippingCost, $fee, $commission));

        return new OrderTotals($subtotal, $cogs, $discount, $shipping, $shippingDiscount, $shippingNet, $shippingCost, $tax, $fee, $feeCharged,
            $total, Money::round($in->paidAmount), $due, $commission, $net, $lineDiscounts, $codAmount, $prepaid, $codFee);
    }

    /**
     * COD extra fee actually CHARGED to the customer (docs/CALCULATIONS.md "COD extra fee"): 0 unless COD + enabled + bearer=customer;
     * base F = subtotal - discount + shipping_net + tax(exclusive); fixed / percent / both, per-city flat override, min/max clamp,
     * waived when subtotal - discount >= free_over. Part of total, cod_amount and income in profit.
     */
    private function codExtraFee(CalculatorInput $in, string $base, string $afterDiscount): string
    {
        $c = $in->codFee;
        if (! $in->isCod || ! $c || ! $c->enabled || $c->bearer !== 'customer') {
            return '0.00';
        }
        if ($c->freeOver !== null && Money::cmp($afterDiscount, $c->freeOver) >= 0) {
            return '0.00';
        }
        if ($in->cityId !== null && isset($c->cityFixed[$in->cityId])) {
            return Money::round($c->cityFixed[$in->cityId]);
        }
        $pct = Money::percent($base, $c->percent);
        $fee = match ($c->type) {
            'percent' => $pct,
            'fixed_plus_percent' => Money::add($c->fixed, bcdiv(bcmul($base, $c->percent, 8), '100', 8)),
            default => Money::round($c->fixed),
        };
        if ($c->min !== null) {
            $fee = Money::max($fee, $c->min);
        }
        if ($c->max !== null) {
            $fee = Money::min($fee, $c->max);
        }

        return $fee;
    }

    /** @return array{0:string,1:array,2:bool} [discount, per-line shares, free shipping?] */
    private function discount(CalculatorInput $in, string $subtotal, array $lineSub): array
    {
        $d = $in->discount;
        if (! $d) {
            return ['0.00', [], false];
        }
        if (Money::cmp($subtotal, $d['min_order'] ?? '0') < 0) {
            return ['0.00', [], false];
        }
        if (! empty($d['free_shipping'])) {
            return ['0.00', [], true]; // free-shipping code: no goods discount
        }

        $eligible = [];
        $base = '0';
        foreach ($in->lines as $i => $l) {
            if ($l['eligible'] ?? true) {
                $eligible[] = $i;
                $base = Money::add($base, $lineSub[$i]);
            }
        }
        if (! $eligible || Money::cmp($base, '0') <= 0) {
            return ['0.00', [], false];
        }

        $raw = $d['type'] === 'percent' ? Money::percent($base, $d['value']) : Money::min($d['value'], $base);
        $cap = $d['max_discount'] ?? null;
        $discount = ($cap !== null && $cap !== '') ? Money::min($raw, $cap) : $raw;
        $discount = Money::min($discount, $base);

        // proportional distribution, each share rounded and clamped to [0, line_subtotal]; the remainder (positive or negative)
        // is then moved onto eligible lines with room / off lines with a positive share, so Σ shares == discount exactly
        $shares = [];
        $given = '0.00';
        foreach ($eligible as $i) {
            $key = $in->lines[$i]['key'];
            $raw = Money::round(bcdiv(bcmul($discount, $lineSub[$i], 8), $base, 8));
            $shares[$key] = Money::max('0', Money::min($raw, $lineSub[$i]));
            $given = Money::add($given, $shares[$key]);
        }
        $left = Money::sub($discount, $given);
        foreach (array_reverse($eligible) as $i) {
            $cmp = Money::cmp($left, '0');
            if ($cmp === 0) {
                break;
            }
            $key = $in->lines[$i]['key'];
            $move = $cmp > 0
                ? Money::min($left, Money::sub($lineSub[$i], $shares[$key]))     // add: up to the line's room
                : Money::max($left, Money::sub('0', $shares[$key]));             // take back: never below 0
            $shares[$key] = Money::add($shares[$key], $move);
            $left = Money::sub($left, $move);
        }

        return [$discount, $shares, false];
    }
}
