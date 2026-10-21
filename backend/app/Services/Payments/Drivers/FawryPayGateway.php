<?php

namespace App\Services\Payments\Drivers;

use App\Services\Payments\AbstractGateway;

class FawryPayGateway extends AbstractGateway
{
    public function key(): string
    {
        return 'fawrypay';
    }

    protected function secretKey(): string
    {
        return 'secure_key';
    }

    protected function signatureHeader(): string
    {
        return 'signature';
    }
}
