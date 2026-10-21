<?php

use App\DTOs\Orders\CalculatorInput;
use App\Services\Orders\OrderCalculator;

function calc(array $over = []): \App\DTOs\Orders\OrderTotals
{
    $args = [
        'lines' => [['key' => 'a', 'unit_price' => '100.00', 'unit_cost' => '60.00', 'qty' => 2], ['key' => 'b', 'unit_price' => '50.00', 'unit_cost' => '30.00', 'qty' => 1]],
        'discount' => ['type' => 'percent', 'value' => '10', 'min_order' => '100', 'max_discount' => '20'],
        'shipping' => ['price' => '30', 'cost' => '25', 'free_over' => '500'],
        'feePercent' => '2.5', 'feeFixed' => '1', 'feeBearer' => 'customer', 'affiliatePercent' => '5',
    ];

    return (new OrderCalculator)->calculate(new CalculatorInput(...array_merge($args, $over)));
}

it('matches the CALCULATIONS.md worked example', function () {
    $t = calc();
    expect($t->subtotal)->toBe('250.00')->and($t->cogs)->toBe('150.00')
        ->and($t->discount)->toBe('20.00')                       // 10% of 250 = 25 capped at 20
        ->and($t->shipping)->toBe('30.00')->and($t->shippingNet)->toBe('30.00')->and($t->shippingCost)->toBe('25.00')
        ->and($t->tax)->toBe('0.00')
        ->and($t->paymentFee)->toBe('7.50')->and($t->paymentFeeCharged)->toBe('7.50')   // F = 260 -> 6.50 + 1
        ->and($t->total)->toBe('267.50')
        ->and($t->affiliateCommission)->toBe('11.50')            // 5% of 230
        ->and($t->netProfit)->toBe('73.50');                     // (230-150)+30-25-7.50-11.50+7.50
    expect(array_sum(array_map('floatval', $t->lineDiscounts)))->toBe(20.0)->and($t->lineDiscounts['a'])->toBe('16.00')->and($t->lineDiscounts['b'])->toBe('4.00');
});

it('merchant-borne fee is a cost, not charged', function () {
    $t = calc(['feeBearer' => 'merchant']);
    expect($t->paymentFee)->toBe('7.50')->and($t->paymentFeeCharged)->toBe('0.00')->and($t->total)->toBe('260.00')
        ->and($t->netProfit)->toBe('66.00');
});

it('applies fixed discount, minimum order and free-shipping thresholds', function () {
    expect(calc(['discount' => ['type' => 'fixed', 'value' => '1000']])->discount)->toBe('250.00');          // min(value, base)
    expect(calc(['discount' => ['type' => 'percent', 'value' => '10', 'min_order' => '300']])->discount)->toBe('0.00');
    $t = calc(['discount' => null, 'shipping' => ['price' => '30', 'cost' => '25', 'free_over' => '250']]);   // 250 >= 250 -> free, still pay carrier
    expect($t->shipping)->toBe('0.00')->and($t->shippingCost)->toBe('25.00');
    $t = calc(['discount' => ['type' => 'percent', 'value' => '10', 'free_shipping' => true]]);
    expect($t->discount)->toBe('0.00')->and($t->shippingDiscount)->toBe('30.00')->and($t->shippingNet)->toBe('0.00');
});

it('limits percent discount to eligible lines and gives the rounding remainder to the last line', function () {
    $t = calc(['lines' => [
        ['key' => 1, 'unit_price' => '10.00', 'unit_cost' => '1', 'qty' => 1, 'eligible' => true],
        ['key' => 2, 'unit_price' => '10.00', 'unit_cost' => '1', 'qty' => 1, 'eligible' => false],
        ['key' => 3, 'unit_price' => '10.00', 'unit_cost' => '1', 'qty' => 1, 'eligible' => true],
    ], 'discount' => ['type' => 'fixed', 'value' => '5.01'], 'shipping' => null, 'feePercent' => '0', 'feeFixed' => '0', 'affiliatePercent' => '0']);
    expect($t->discount)->toBe('5.01')->and($t->lineDiscounts)->toBe([1 => '2.51', 3 => '2.50'])->and($t->total)->toBe('24.99');
});

it('handles exclusive and inclusive tax', function () {
    $ex = calc(['discount' => null, 'shipping' => null, 'feePercent' => '0', 'feeFixed' => '0', 'taxRate' => '14', 'affiliatePercent' => '0']);
    expect($ex->tax)->toBe('35.00')->and($ex->total)->toBe('285.00');
    $in = calc(['discount' => null, 'shipping' => null, 'feePercent' => '0', 'feeFixed' => '0', 'taxRate' => '14', 'pricesIncludeTax' => true, 'affiliatePercent' => '0']);
    expect($in->tax)->toBe('30.70')->and($in->total)->toBe('250.00');   // 250 * 14/114 = 30.70 (included)
});
