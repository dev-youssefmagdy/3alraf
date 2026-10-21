<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum TransferStatus: string
{
    use HasLabels;

    case DRAFT = 'draft';
    case IN_TRANSIT = 'in_transit';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';
}
