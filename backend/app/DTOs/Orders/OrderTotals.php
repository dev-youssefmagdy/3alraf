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
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
