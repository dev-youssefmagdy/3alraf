<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum FeeBearer: string
{
    use HasLabels;

    case CUSTOMER = 'customer';
    case MERCHANT = 'merchant';
}
