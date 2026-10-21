<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum AdjustmentReason: string
{
    use HasLabels;

    case COUNT = 'count';
    case DAMAGED = 'damaged';
    case LOST = 'lost';
    case FOUND = 'found';
    case EXPIRED = 'expired';
    case CORRECTION = 'correction';
    case OTHER = 'other';
}
