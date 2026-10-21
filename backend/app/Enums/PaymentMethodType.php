<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum PaymentMethodType: string
{
    use HasLabels;

    case COD = 'cod';
    case WALLET = 'wallet';
    case ONLINE = 'online';
}
