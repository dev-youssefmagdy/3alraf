<?php

namespace App\Enums;

enum AttributeType: string
{
    case SELECT = 'select';
    case COLOR = 'color';
    case TEXT = 'text';

    public function label(): string
    {
        return __('admin.attribute_types.'.$this->value);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
