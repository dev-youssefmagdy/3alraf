<?php

namespace App\Enums;

enum TextDirection: string
{
    case LTR = 'ltr';
    case RTL = 'rtl';

    public function label(): string
    {
        return __('admin.direction_'.$this->value);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
