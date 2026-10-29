<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum StockRequestStatus: string
{
    use HasLabels;

    case NEW = 'new';
    case CONTACTED = 'contacted';
    case CLOSED = 'closed';

    public function label(): string
    {
        return __('stock_requests.statuses.'.$this->value);
    }
}
