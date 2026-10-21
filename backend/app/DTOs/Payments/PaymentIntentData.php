<?php

namespace App\DTOs\Payments;

/** Input for starting an online payment. Amount in tenant currency (decimal string). */
final readonly class PaymentIntentData
{
    public function __construct(
        public string $orderReference,
        public string $amount,
        public string $currency,
        public array $customer = [],   // name,email,phone
        public ?string $returnUrl = null,
        public ?string $webhookUrl = null,
    ) {}
}
