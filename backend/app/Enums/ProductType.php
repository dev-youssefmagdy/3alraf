<?php

namespace App\Enums;

enum ProductType: string
{
    case SIMPLE = 'simple';
    case VARIABLE = 'variable';

    public function label(): string
    {
        return __('admin.product_types.'.$this->value);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
