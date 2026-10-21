<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum StockMovementType: string
{
    use HasLabels;

    case PURCHASE_IN = 'purchase_in';
    case SALE_OUT = 'sale_out';
    case RETURN_IN = 'return_in';
    case TRANSFER_OUT = 'transfer_out';
    case TRANSFER_IN = 'transfer_in';
    case ADJUSTMENT_IN = 'adjustment_in';
    case ADJUSTMENT_OUT = 'adjustment_out';

    /** +1 increases on_hand, -1 decreases. */
    public function sign(): int
    {
        return match ($this) {
            self::PURCHASE_IN, self::RETURN_IN, self::TRANSFER_IN, self::ADJUSTMENT_IN => 1,
            default => -1,
        };
    }

    /** Incoming types that re-average cost with their own unit cost. */
    public function recalculatesAverage(): bool
    {
        return $this->sign() === 1;
    }
}
