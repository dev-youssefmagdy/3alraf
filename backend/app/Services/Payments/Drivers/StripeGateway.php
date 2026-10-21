<?php

namespace App\Services\Payments\Drivers;

use App\Services\Payments\AbstractGateway;

class StripeGateway extends AbstractGateway
{
    public function key(): string
    {
        return 'stripe';
    }

    protected function secretKey(): string
    {
        return 'webhook_secret';
    }

    protected function signatureHeader(): string
    {
        return 'Stripe-Signature';
    }
}
