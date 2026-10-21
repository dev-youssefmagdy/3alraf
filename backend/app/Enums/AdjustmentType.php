<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum AdjustmentType: string
{
    use HasLabels;

    case INCREASE = 'increase';
    case DECREASE = 'decrease';
}
