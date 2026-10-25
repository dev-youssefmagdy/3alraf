<?php

use App\DTOs\Orders\CalculatorInput;
use App\Models\Order;
use App\Services\Orders\OrderCalculator;
use App\Services\Orders\ReturnService;

function calcInput(array $lines, array $discount): CalculatorInput
{
    return new CalculatorInput(lines: $lines, discount: $discount);
}

it('never gives a line a discount larger than its subtotal and always distributes the full discount', function () {
    mt_srand(7);
    $calc = new OrderCalculator;
    for ($n = 0; $n < 300; $n++) {
        $lines = [];
        $count = mt_rand(2, 6);
        for ($i = 0; $i < $count; $i++) {
            $lines[] = ['key' => $i, 'product_id' => $i + 1, 'unit_price' => number_format(mt_rand(1, 5000) / 100, 2, '.', ''), 'unit_cost' => '0', 'qty' => mt_rand(1, 3), 'eligible' => true];
        }
        $t = $calc->calculate(calcInput($lines, ['type' => 'fixed', 'value' => number_format(mt_rand(1, 20000) / 100, 2, '.', ''), 'min_order' => '0', 'max_discount' => null]));
        $sum = '0';
        foreach ($lines as $l) {
            $share = $t->lineDiscounts[$l['key']] ?? '0.00';
            expect(bccomp($share, bcmul($l['unit_price'], (string) $l['qty'], 2), 2))->toBeLessThanOrEqual(0);
            $sum = bcadd($sum, $share, 2);
        }
        expect(bccomp($sum, $t->discount, 2))->toBe(0);
    }
});

it('refunds tax pro-rata only on tax-exclusive orders', function () {
    $svc = new ReturnService(Mockery::mock(\App\Repositories\Contracts\OrderReturnRepositoryInterface::class), Mockery::mock(\App\Services\Stock\StockLedger::class), Mockery::mock(\App\Services\Payments\RefundService::class), new \App\Services\Orders\OrderProfitService);
    // exclusive: goods 200, 14% tax = 28 added on top (total 228); returning 100 of goods refunds 14 tax
    $excl = (new Order)->forceFill(['subtotal' => '200.00', 'discount' => '0.00', 'tax' => '28.00', 'shipping_net' => '0.00', 'payment_fee_charged' => '0.00', 'total' => '228.00']);
    expect($svc->taxShare($excl, '100.00'))->toBe('14.00');
    // inclusive: total == goods (tax already inside), nothing extra to refund
    $incl = (new Order)->forceFill(['subtotal' => '200.00', 'discount' => '0.00', 'tax' => '24.56', 'shipping_net' => '0.00', 'payment_fee_charged' => '0.00', 'total' => '200.00']);
    expect($svc->taxShare($incl, '100.00'))->toBe('0.00');
});

it('never gives a line a NEGATIVE discount share and still distributes the exact discount (rounding overshoot)', function () {
    // 0.02 over 1+1+1+0.01: proportional shares round to 0.01 each (0.03 > 0.02); the old "last takes the remainder" gave -0.01
    $lines = [];
    foreach (['1.00', '1.00', '1.00', '0.01'] as $i => $p) {
        $lines[] = ['key' => $i, 'unit_price' => $p, 'unit_cost' => '0', 'qty' => 1, 'eligible' => true];
    }
    $t = (new OrderCalculator)->calculate(calcInput($lines, ['type' => 'fixed', 'value' => '0.02', 'min_order' => '0', 'max_discount' => null]));
    expect(array_sum(array_map('floatval', $t->lineDiscounts)))->toEqualWithDelta(0.02, 0.0001);
    foreach ($t->lineDiscounts as $share) {
        expect(bccomp($share, '0', 2))->toBeGreaterThanOrEqual(0);
    }

    mt_srand(11);
    for ($n = 0; $n < 300; $n++) {
        $lines = [];
        for ($i = 0, $c = mt_rand(2, 7); $i < $c; $i++) {
            $lines[] = ['key' => $i, 'unit_price' => number_format(mt_rand(1, 300) / 100, 2, '.', ''), 'unit_cost' => '0', 'qty' => 1, 'eligible' => true];
        }
        $t = (new OrderCalculator)->calculate(calcInput($lines, ['type' => 'fixed', 'value' => number_format(mt_rand(1, 50) / 100, 2, '.', ''), 'min_order' => '0', 'max_discount' => null]));
        $sum = '0';
        foreach ($lines as $l) {
            $s = $t->lineDiscounts[$l['key']] ?? '0.00';
            expect(bccomp($s, '0', 2))->toBeGreaterThanOrEqual(0)->and(bccomp($s, $l['unit_price'], 2))->toBeLessThanOrEqual(0);
            $sum = bcadd($sum, $s, 2);
        }
        expect(bccomp($sum, $t->discount, 2))->toBe(0);
    }
});

it('excludes VAT of tax-inclusive prices from commission and profit (worked example 1 x 114, 14% inclusive)', function () {
    $t = (new OrderCalculator)->calculate(new CalculatorInput(
        lines: [['key' => 0, 'unit_price' => '114.00', 'unit_cost' => '50.00', 'qty' => 1, 'eligible' => true]],
        taxRate: '14', pricesIncludeTax: true, affiliatePercent: '10',
    ));
    expect($t->tax)->toBe('14.00')->and($t->total)->toBe('114.00')->and($t->affiliateCommission)->toBe('10.00')->and($t->netProfit)->toBe('40.00');

    $o = (new Order)->forceFill(['subtotal' => '114.00', 'discount' => '0.00', 'tax' => '14.00', 'shipping_net' => '0.00', 'payment_fee_charged' => '0.00', 'cod_fee' => '0.00', 'total' => '114.00']);
    expect($o->taxIncluded())->toBeTrue()->and($o->revenueGoods())->toBe('100.00')->and($o->returnNetOfTax('114.00'))->toBe('100.00');
    $x = (new Order)->forceFill(['subtotal' => '100.00', 'discount' => '0.00', 'tax' => '14.00', 'shipping_net' => '0.00', 'payment_fee_charged' => '0.00', 'cod_fee' => '0.00', 'total' => '114.00']);
    expect($x->taxIncluded())->toBeFalse()->and($x->revenueGoods())->toBe('100.00')->and($x->returnNetOfTax('50.00'))->toBe('50.00');
});

it('makes partial returns sum exactly to the line net and the tax (cumulative, exact remainder on the last unit)', function () {
    $svc = new ReturnService(Mockery::mock(\App\Repositories\Contracts\OrderReturnRepositoryInterface::class), Mockery::mock(\App\Services\Stock\StockLedger::class), Mockery::mock(\App\Services\Payments\RefundService::class), new \App\Services\Orders\OrderProfitService);
    $item = (new \App\Models\OrderItem)->forceFill(['unit_price' => '100.00', 'quantity' => 3, 'line_discount' => '10.00', 'returned_qty' => 0]);
    $parts = [$svc->lineValue($item, 1, 0), $svc->lineValue($item, 1, 1), $svc->lineValue($item, 1, 2)];
    expect($parts)->toBe(['96.67', '96.66', '96.67'])->and(bcadd(bcadd($parts[0], $parts[1], 2), $parts[2], 2))->toBe('290.00');
    expect($svc->lineValue($item, 3))->toBe('290.00');

    $o = (new Order)->forceFill(['subtotal' => '300.00', 'discount' => '0.00', 'tax' => '10.00', 'shipping_net' => '0.00', 'payment_fee_charged' => '0.00', 'cod_fee' => '0.00', 'total' => '310.00']);
    $tax = [$svc->taxShare($o, '100.00', '0'), $svc->taxShare($o, '100.00', '100.00'), $svc->taxShare($o, '100.00', '200.00')];
    expect($tax)->toBe(['3.33', '3.34', '3.33']);
});
