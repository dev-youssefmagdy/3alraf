<?php

namespace App\Services\Payments\Drivers;

use App\Services\Payments\AbstractGateway;

class PayTabsGateway extends AbstractGateway
{
    public function key(): string
    {
        return 'paytabs';
    }

    protected function secretKey(): string
    {
        return 'server_key';
    }

    protected function signatureHeader(): string
    {
        return 'Signature';
    }
}
