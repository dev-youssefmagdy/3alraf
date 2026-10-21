<?php

namespace App\DTOs\Payments;

/** Normalised gateway response. status: pending|paid|failed|cancelled */
final readonly class PaymentResult
{
    public function __construct(public string $status, public ?string $gatewayReference = null, public ?string $redirectUrl = null, public array $raw = []) {}
}
