<?php

namespace App\Services\Payments\Drivers;

use App\Services\Payments\AbstractGateway;

class PaymobGateway extends AbstractGateway
{
    public function key(): string
    {
        return 'paymob';
    }

    protected function secretKey(): string
    {
        return 'hmac_secret';
    }

    protected function signatureHeader(): string
    {
        return 'hmac';
    }
}
