<?php

use App\DTOs\Orders\CalculatorInput;
use App\DTOs\Orders\CodFeeConfig;
use App\Models\Order;
use App\Services\Orders\OrderCalculator;

function pcalc(array $over = []): \App\DTOs\Orders\OrderTotals
{
    $args = [
        'lines' => [['key' => 'a', 'unit_price' => '100.00', 'unit_cost' => '60.00', 'qty' => 2], ['key' => 'b', 'unit_price' => '50.00', 'unit_cost' => '30.00', 'qty' => 1]],
        'discount' => ['type' => 'percent', 'value' => '10', 'min_order' => '100', 'max_discount' => '20'],
        'shipping' => ['price' => '30', 'cost' => '25', 'free_over' => '500'],
        'feePercent' => '2.5', 'feeFixed' => '1', 'feeBearer' => 'customer', 'affiliatePercent' => '5',
        'packagingFee' => '15', 'packagingCost' => '4',
    ];

    return (new OrderCalculator)->calculate(new CalculatorInput(...array_merge($args, $over)));
}

it('adds packaging as its own step: worked example (fee base, total, profit)', function () {
    $t = pcalc();
    expect($t->discount)->toBe('20.00')                     // discount base = goods only (packaging not discountable)
        ->and($t->packagingFee)->toBe('15.00')->and($t->packagingTax)->toBe('0.00')
        ->and($t->paymentFee)->toBe('7.88')                 // F = 230 + 30 + 15 = 275 -> 6.875 + 1
        ->and($t->total)->toBe('282.88')
        ->and($t->affiliateCommission)->toBe('11.50')       // goods only
        ->and($t->netProfit)->toBe('84.50');                // 73.50 + (15 - 4)
});

it('changes nothing when there is no packaging', function () {
    $t = pcalc(['packagingFee' => '0', 'packagingCost' => '0']);
    expect($t->total)->toBe('267.50')->and($t->netProfit)->toBe('73.50')->and($t->packagingFee)->toBe('0.00');
});

it('keeps packaging out of the discount base and the free-shipping threshold', function () {
    // discount 10% on goods only: 250 -> 25 even though packaging is 500
    expect(pcalc(['packagingFee' => '500', 'discount' => ['type' => 'percent', 'value' => '10']])->discount)->toBe('25.00');
    // goods after discount 230 < free_over 240 <= 230 + 15: shipping must NOT become free
    $t = pcalc(['shipping' => ['price' => '30', 'cost' => '25', 'free_over' => '240']]);
    expect($t->shipping)->toBe('30.00')->and($t->shippingNet)->toBe('30.00');
    // a min_order that only goods + packaging would meet is not met
    expect(pcalc(['discount' => ['type' => 'percent', 'value' => '10', 'min_order' => '260']])->discount)->toBe('0.00');
});

it('adds packaging VAT on top for tax-exclusive tenants and includes it in the fee base', function () {
    $t = pcalc(['taxRate' => '14']);
    expect($t->tax)->toBe('32.20')->and($t->packagingTax)->toBe('2.10')
        ->and($t->paymentFee)->toBe('8.73')                  // F = 230 + 30 + 32.20 + 15 + 2.10 = 309.30
        ->and($t->total)->toBe('318.03')
        ->and($t->forCustomer()['tax'])->toBe('34.30')->and($t->forCustomer()['packagingFee'])->toBe('15.00')
        ->and($t->netProfit)->toBe('84.50');                 // VAT is pass-through
});

it('extracts VAT from the packaging price for tax-inclusive tenants and keeps it out of revenue/profit', function () {
    $t = pcalc(['taxRate' => '14', 'pricesIncludeTax' => true, 'lines' => [['key' => 'a', 'unit_price' => '114.00', 'unit_cost' => '50.00', 'qty' => 1]],
        'discount' => null, 'shipping' => null, 'feePercent' => '0', 'feeFixed' => '0', 'affiliatePercent' => '0', 'packagingFee' => '114', 'packagingCost' => '10']);
    expect($t->tax)->toBe('14.00')->and($t->packagingTax)->toBe('14.00')->and($t->total)->toBe('228.00')
        ->and($t->netProfit)->toBe('140.00');                // (100 - 50) + (100 - 10)

    // the order detects itself as inclusive from the stored columns with packaging inside the total
    $o = (new Order)->forceFill(['subtotal' => '114.00', 'discount' => '0', 'shipping_net' => '0', 'payment_fee_charged' => '0', 'cod_fee' => '0',
        'tax' => '14.00', 'packaging_fee' => '114.00', 'packaging_tax' => '14.00', 'packaging_cost' => '10.00', 'total' => '228.00']);
    expect($o->taxIncluded())->toBeTrue()->and($o->revenueGoods())->toBe('100.00')->and($o->packagingRevenue())->toBe('100.00');
    // exclusive order with packaging is NOT detected as inclusive
    $x = (new Order)->forceFill(['subtotal' => '100.00', 'discount' => '0', 'shipping_net' => '0', 'payment_fee_charged' => '0', 'cod_fee' => '0',
        'tax' => '14.00', 'packaging_fee' => '10.00', 'packaging_tax' => '1.40', 'total' => '125.40']);
    expect($x->taxIncluded())->toBeFalse()->and($x->packagingRevenue())->toBe('10.00');
});

it('puts packaging in the COD fee base and cod_amount but not in prepaid shipping', function () {
    $cod = CodFeeConfig::fromSettings(['cod_extra_fee_enabled' => true, 'cod_extra_fee_type' => 'percent', 'cod_extra_fee_percent' => '2']);
    $with = pcalc(['feePercent' => '0', 'feeFixed' => '0', 'isCod' => true, 'codFee' => $cod]);
    $without = pcalc(['feePercent' => '0', 'feeFixed' => '0', 'isCod' => true, 'codFee' => $cod, 'packagingFee' => '0', 'packagingCost' => '0']);
    expect($without->codFee)->toBe('5.20')->and($with->codFee)->toBe('5.50')       // 2% of 260 vs 2% of 275
        ->and($with->total)->toBe('280.50')                                         // 230 + 30 + 15 + 5.50
        ->and($with->prepaidShipping)->toBe('30.00')->and($with->codAmount)->toBe('250.50');
});

it('does not charge the packaging when it is free of cost and price (zero option)', function () {
    $t = pcalc(['packagingFee' => '0.00', 'packagingCost' => '3']);
    expect($t->total)->toBe('267.50')->and($t->netProfit)->toBe('70.50');          // cost still reduces profit
});
