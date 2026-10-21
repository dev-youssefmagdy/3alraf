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
    $svc = new ReturnService(Mockery::mock(\App\Repositories\Contracts\OrderReturnRepositoryInterface::class), Mockery::mock(\App\Services\Stock\StockLedger::class), Mockery::mock(\App\Services\Payments\PaymentGatewayFactory::class));
    // exclusive: goods 200, 14% tax = 28 added on top (total 228); returning 100 of goods refunds 14 tax
    $excl = (new Order)->forceFill(['subtotal' => '200.00', 'discount' => '0.00', 'tax' => '28.00', 'shipping_net' => '0.00', 'payment_fee_charged' => '0.00', 'total' => '228.00']);
    expect($svc->taxShare($excl, '100.00'))->toBe('14.00');
    // inclusive: total == goods (tax already inside), nothing extra to refund
    $incl = (new Order)->forceFill(['subtotal' => '200.00', 'discount' => '0.00', 'tax' => '24.56', 'shipping_net' => '0.00', 'payment_fee_charged' => '0.00', 'total' => '200.00']);
    expect($svc->taxShare($incl, '100.00'))->toBe('0.00');
});
