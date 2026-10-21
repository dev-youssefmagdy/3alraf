<?php

namespace App\Services\Payments\Drivers;

use App\Services\Payments\AbstractGateway;

class PayPalGateway extends AbstractGateway
{
    public function key(): string
    {
        return 'paypal';
    }

    protected function secretKey(): string
    {
        return 'webhook_id';
    }

    protected function signatureHeader(): string
    {
        return 'Paypal-Transmission-Sig';
    }
}
