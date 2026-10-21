<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use Illuminate\Support\Str;

enum OrderStatus: string
{
    use HasLabels;

    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
    case RETURNED = 'returned';

    public function label(): string
    {
        return __('orders.enums.'.Str::snake('OrderStatus').'.'.$this->value);
    }

    /** Allowed manual transitions (returned is reached only via the returns flow). */
    public function next(): array
    {
        return match ($this) {
            self::PENDING => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED => [self::PROCESSING, self::CANCELLED],
            self::PROCESSING => [self::SHIPPED, self::CANCELLED],
            self::SHIPPED => [self::DELIVERED],
            default => [],
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DELIVERED => 'badge-success',
            self::CANCELLED, self::RETURNED => 'badge-danger',
            default => 'badge-muted',
        };
    }

    /** Statuses counted as sales (confirmed -> delivered) for reports. */
    public static function salesStatuses(): array
    {
        return [self::CONFIRMED, self::PROCESSING, self::SHIPPED, self::DELIVERED];
    }
}
