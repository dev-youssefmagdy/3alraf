<?php

namespace App\Support;

use Illuminate\Support\Str;

class Slug
{
    /** Unique slug for $modelClass.slug (soft-deleted rows included). */
    public static function unique(string $modelClass, ?string $wanted, array|string|null $fallbackText, ?int $ignoreId = null): string
    {
        $text = $wanted ?: (is_array($fallbackText) ? (collect($fallbackText)->first(fn ($v) => $v) ?? '') : (string) $fallbackText);
        $base = Str::slug($text) ?: 'item-'.Str::lower(Str::random(6));
        $slug = $base;
        $i = 2;
        $q = fn () => $modelClass::query()->when(method_exists($modelClass, 'bootSoftDeletes'), fn ($q) => $q->withTrashed())
            ->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists();
        while ($q()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
