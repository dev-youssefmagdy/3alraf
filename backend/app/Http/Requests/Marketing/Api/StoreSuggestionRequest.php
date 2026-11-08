<?php

namespace App\Http\Requests\Marketing\Api;

use App\Services\Marketing\Suggestions\Contracts\SuggestionType;
use App\Services\Marketing\Suggestions\SuggestionTypeRegistry;

/** POST /api/internal/marketing/suggestions/{type} — the type's own payload schema (SuggestionType::rules) + common fields. */
class StoreSuggestionRequest extends SuggestionWriteRequest
{
    protected function handler(): ?SuggestionType
    {
        $r = app(SuggestionTypeRegistry::class);

        return $r->has((string) $this->route('type')) ? $r->get((string) $this->route('type')) : null;
    }

    protected function targetType(): ?string
    {
        return is_string($t = $this->input('target_entity_type')) ? $t : null;
    }

    protected function creating(): bool
    {
        return true;
    }
}
