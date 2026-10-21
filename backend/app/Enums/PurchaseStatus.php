<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum PurchaseStatus: string
{
    use HasLabels;

    case DRAFT = 'draft';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';
}
