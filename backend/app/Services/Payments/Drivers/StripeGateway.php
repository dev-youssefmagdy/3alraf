<?php

namespace App\Services\Payments\Drivers;

use App\Services\Payments\NotImplementedGateway;

/** Not implemented yet (see NotImplementedGateway). */
class StripeGateway extends NotImplementedGateway
{
    public function key(): string
    {
        return 'stripe';
    }
}
