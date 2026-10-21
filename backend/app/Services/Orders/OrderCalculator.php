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

        // 5/6. Total, due
        $total = Money::add($afterDiscount, $shippingNet, $taxAdded, $feeCharged);
        $due = Money::sub($total, $in->paidAmount);

        // Affiliate commission on goods after discount
        $commission = bccomp($in->affiliatePercent, '0', 6) > 0 ? Money::percent($afterDiscount, $in->affiliatePercent) : '0.00';

        // Profit: (revenue_goods - cogs) + shipping_net - shipping_cost - fee - commission + fee_charged
        $net = Money::sub(Money::add(Money::sub($afterDiscount, $cogs), $shippingNet, $feeCharged), Money::add($shippingCost, $fee, $commission));

        return new OrderTotals($subtotal, $cogs, $discount, $shipping, $shippingDiscount, $shippingNet, $shippingCost, $tax, $fee, $feeCharged,
            $total, Money::round($in->paidAmount), $due, $commission, $net, $lineDiscounts);
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

        // proportional distribution; last eligible line takes the rounding remainder, but no line_discount may exceed its
        // line_subtotal (any excess is pushed to eligible lines that still have room, so Σ shares == discount)
        $shares = [];
        $given = '0.00';
        foreach ($eligible as $n => $i) {
            $key = $in->lines[$i]['key'];
            $raw = $n === array_key_last($eligible) ? Money::sub($discount, $given) : Money::round(bcdiv(bcmul($discount, $lineSub[$i], 8), $base, 8));
            $shares[$key] = Money::min($raw, $lineSub[$i]);
            $given = Money::add($given, $shares[$key]);
        }
        $left = Money::sub($discount, $given);
        foreach ($eligible as $i) {
            if (Money::cmp($left, '0') <= 0) {
                break;
            }
            $key = $in->lines[$i]['key'];
            $take = Money::min($left, Money::sub($lineSub[$i], $shares[$key]));
            $shares[$key] = Money::add($shares[$key], $take);
            $left = Money::sub($left, $take);
        }

        return [$discount, $shares, false];
    }
}
