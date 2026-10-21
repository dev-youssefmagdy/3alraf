<?php

namespace App\Enums\Concerns;

use Illuminate\Support\Str;

/** label() = lang `ops.enums.{enum_snake}.{value}`; options() = value => label. */
trait HasLabels
{
    public function label(): string
    {
        return __('ops.enums.'.Str::snake(class_basename(static::class)).'.'.$this->value);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }

    public static function values(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }
}
