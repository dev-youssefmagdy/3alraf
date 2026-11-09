<?php

namespace App\Support\Marketing;

use App\Enums\Marketing\SuggestionTypeKey;

/** One Marketing API write scope per suggestion type: `suggestions:write:{type}`. Never covers approve / reject / apply (dashboard-only). */
final class SuggestionScopes
{
    public const PREFIX = 'suggestions:write:';

    public static function for(SuggestionTypeKey|string $type): string
    {
        return self::PREFIX.($type instanceof SuggestionTypeKey ? $type->value : $type);
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_map(self::for(...), SuggestionTypeKey::values());
    }
}
