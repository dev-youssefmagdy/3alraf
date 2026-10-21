<?php

namespace App\DTOs\Orders;

/**
 * Pure input of App\Services\Orders\OrderCalculator (docs/CALCULATIONS.md).
 *
 * @param  array<int,array{key:string|int,unit_price:string,unit_cost:string,qty:int,eligible?:bool}>  $lines
 * @param  array{type:string,value:string,min_order?:string,max_discount?:?string,free_shipping?:bool}|null  $discount  type = percent|fixed
 * @param  array{price:string,cost?:?string,free_over?:?string}|null  $shipping
 */
final readonly class CalculatorInput
{
    public function __construct(
        public array $lines,
        public ?array $discount = null,
        public ?array $shipping = null,
        public string $taxRate = '0',          // percent, e.g. 14
        public bool $pricesIncludeTax = false,
        public string $feePercent = '0',
        public string $feeFixed = '0',
        public string $feeBearer = 'customer', // customer|merchant
        public string $affiliatePercent = '0',
        public string $paidAmount = '0',
        public bool $isCod = false,                    // payment method type is COD
        public bool $codCollectShippingUpfront = true, // settings.cod_collect_shipping_upfront
        public ?CodFeeConfig $codFee = null,           // tenant COD extra fee settings (null = none)
        public ?int $cityId = null,                    // for the per-city COD fee override
    ) {}
}
