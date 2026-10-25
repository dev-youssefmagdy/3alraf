<?php

namespace App\DTOs\Orders;

/** Result of OrderCalculator. All money values are 2-dp strings. */
final readonly class OrderTotals
{
    /** @param array<string|int,string> $lineDiscounts line key => discount share */
    public function __construct(
        public string $subtotal, public string $cogs, public string $discount, public string $shipping, public string $shippingDiscount,
        public string $shippingNet, public string $shippingCost, public string $tax, public string $paymentFee, public string $paymentFeeCharged,
        public string $total, public string $paidAmount, public string $due, public string $affiliateCommission, public string $netProfit,
        public array $lineDiscounts = [],
        public string $codAmount = '0.00', public string $prepaidShipping = '0.00', public string $codFee = '0.00',
        public string $packagingFee = '0.00', public string $packagingTax = '0.00', public string $packagingCost = '0.00',
    ) {}

    /** What a shopper may see. NEVER send toArray() to a client: it carries cogs, net profit, carrier cost and affiliate commission. */
    public function forCustomer(): array
    {
        return [
            'subtotal' => $this->subtotal, 'discount' => $this->discount, 'shipping' => $this->shipping, 'shippingDiscount' => $this->shippingDiscount,
            'shippingNet' => $this->shippingNet, 'tax' => \App\Support\Money::add($this->tax, $this->packagingTax), 'packagingFee' => $this->packagingFee, 'paymentFeeCharged' => $this->paymentFeeCharged, 'total' => $this->total,
            'codAmount' => $this->codAmount, 'prepaidShipping' => $this->prepaidShipping, 'codFee' => $this->codFee,
        ];
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
