<?php

namespace App\Enums;

/** Structured reason of a return (order_returns.reason_code) or a cancellation (orders.cancel_reason_code). Labels: lang orders.reason_codes. */
enum ReturnReasonCode: string
{
    case COLOR_DIFFERS = 'color_differs';
    case SIZE_UNCLEAR = 'size_unclear';
    case DAMAGED_PACKAGING = 'damaged_packaging';
    case DELIVERY_DELAY = 'delivery_delay';
    case DESCRIPTION_INACCURATE = 'description_inaccurate';
    case QUALITY = 'quality';
    case WRONG_ITEM = 'wrong_item';
    case CHANGED_MIND = 'changed_mind';
    case OTHER = 'other';

    public function label(): string
    {
        return __('orders.reason_codes.'.$this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return array<string,string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
