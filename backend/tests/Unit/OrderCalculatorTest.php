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

it('splits COD: customer prepays shipping_net, the carrier collects the rest (worked example extended)', function () {
    $t = calc(['isCod' => true, 'codCollectShippingUpfront' => true]);
    expect($t->total)->toBe('267.50')->and($t->prepaidShipping)->toBe('30.00')->and($t->codAmount)->toBe('237.50')   // 267.50 - 30
        ->and($t->netProfit)->toBe('73.50');                                                                        // split never changes profit
    $full = calc(['isCod' => true, 'codCollectShippingUpfront' => false]);                                          // setting off: carrier collects it all
    expect($full->prepaidShipping)->toBe('0.00')->and($full->codAmount)->toBe('267.50');
    $free = calc(['isCod' => true, 'shipping' => ['price' => '30', 'cost' => '25', 'free_over' => '200']]);          // free shipping: nothing to prepay
    expect($free->shippingNet)->toBe('0.00')->and($free->prepaidShipping)->toBe('0.00')->and($free->codAmount)->toBe($free->total);
    $card = calc();                                                                                                 // non-COD: no cod_amount
    expect($card->codAmount)->toBe('0.00')->and($card->prepaidShipping)->toBe('0.00');
});

/* ---------- COD extra fee (CALCULATIONS.md "COD extra fee"); base F = 260 in the worked example ---------- */
function codCalc(array $cfg = [], array $over = []): \App\DTOs\Orders\OrderTotals
{
    $c = \App\DTOs\Orders\CodFeeConfig::fromSettings(array_merge(['cod_extra_fee_enabled' => true, 'cod_extra_fee_type' => 'fixed', 'cod_extra_fee_fixed' => '10'], $cfg));

    return calc(array_merge(['feePercent' => '0', 'feeFixed' => '0', 'isCod' => true, 'codFee' => $c], $over));
}

it('COD fee fixed + percent: total, cod_amount (not prepaid shipping) and profit', function () {
    $t = codCalc(['cod_extra_fee_type' => 'fixed_plus_percent', 'cod_extra_fee_percent' => '2'], ['codCollectShippingUpfront' => false]);
    expect($t->codFee)->toBe('15.20')->and($t->total)->toBe('275.20')->and($t->prepaidShipping)->toBe('0.00')->and($t->codAmount)->toBe('275.20')
        ->and($t->netProfit)->toBe('88.70');
    $u = codCalc(['cod_extra_fee_type' => 'fixed_plus_percent', 'cod_extra_fee_percent' => '2'], ['codCollectShippingUpfront' => true]);
    expect($u->prepaidShipping)->toBe('30.00')->and($u->codAmount)->toBe('245.20');   // fee is collected by the carrier, never prepaid
    expect($t->forCustomer())->toHaveKey('codFee')->not->toHaveKeys(['netProfit', 'cogs']);
});

it('COD fee fixed, percent, min, max, free_over', function () {
    expect(codCalc()->codFee)->toBe('10.00')->and(codCalc()->total)->toBe('270.00');
    expect(codCalc(['cod_extra_fee_type' => 'percent', 'cod_extra_fee_percent' => '7'])->codFee)->toBe('18.20');
    expect(codCalc(['cod_extra_fee_type' => 'percent', 'cod_extra_fee_percent' => '2', 'cod_extra_fee_min' => '20'])->codFee)->toBe('20.00')   // 5.20 floored
        ->and(codCalc(['cod_extra_fee_type' => 'percent', 'cod_extra_fee_percent' => '10', 'cod_extra_fee_max' => '12'])->codFee)->toBe('12.00') // 26 capped
        ->and(codCalc(['cod_extra_fee_type' => 'fixed', 'cod_extra_fee_fixed' => '50', 'cod_extra_fee_max' => '12'])->codFee)->toBe('12.00');
    expect(codCalc(['cod_extra_fee_free_over' => '230'])->codFee)->toBe('0.00')->and(codCalc(['cod_extra_fee_free_over' => '230.01'])->codFee)->toBe('10.00'); // subtotal-discount = 230
});

it('COD fee: merchant bearer charges nothing, non-COD and disabled are 0, per-city override replaces the formula', function () {
    $m = codCalc(['cod_extra_fee_bearer' => 'merchant']);
    expect($m->codFee)->toBe('0.00')->and($m->total)->toBe('260.00')->and($m->netProfit)->toBe('73.50');
    expect(codCalc([], ['isCod' => false])->codFee)->toBe('0.00')->and(codCalc([], ['isCod' => false])->total)->toBe('260.00');
    expect(codCalc(['cod_extra_fee_enabled' => false])->codFee)->toBe('0.00');
    $city = ['cod_extra_fee_city_fixed' => [5 => '25']];
    expect(codCalc($city, ['cityId' => 5])->codFee)->toBe('25.00')->and(codCalc($city, ['cityId' => 6])->codFee)->toBe('10.00')
        ->and(codCalc($city + ['cod_extra_fee_free_over' => '100'], ['cityId' => 5])->codFee)->toBe('0.00');
});

it('COD fee uses the payment-fee base (incl. exclusive tax) and rounds half-up at the end', function () {
    // F = 230 + 30 + 14% tax of 230 (32.20) = 292.20 ; 0.333% = 0.97303 -> 0.5 + 0.97303 = 1.47303 -> 1.47
    $t = codCalc(['cod_extra_fee_type' => 'fixed_plus_percent', 'cod_extra_fee_fixed' => '0.5', 'cod_extra_fee_percent' => '0.333'], ['taxRate' => '14']);
    expect($t->codFee)->toBe('1.47')->and($t->total)->toBe('293.67');
    expect(codCalc(['cod_extra_fee_type' => 'percent', 'cod_extra_fee_percent' => '0.333'])->codFee)->toBe('0.87'); // 0.8658
});
