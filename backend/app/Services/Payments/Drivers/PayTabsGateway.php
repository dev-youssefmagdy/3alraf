<?php

namespace App\Services\Payments\Drivers;

use App\Services\Payments\NotImplementedGateway;

/** Not implemented yet (see NotImplementedGateway). */
class PayTabsGateway extends NotImplementedGateway
{
    public function key(): string
    {
        return 'paytabs';
    }
}
