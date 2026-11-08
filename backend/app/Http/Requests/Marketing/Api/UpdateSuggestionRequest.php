<?php

namespace App\Http\Requests\Marketing\Api;

use App\Models\MarketingSuggestion;
use App\Services\Marketing\Suggestions\Contracts\SuggestionType;
use App\Services\Marketing\Suggestions\SuggestionTypeRegistry;

/** PATCH /api/internal/marketing/suggestions/{id} — own pending/edited draft only (checked by the engine). `payload`, when sent, is the full new payload. */
class UpdateSuggestionRequest extends SuggestionWriteRequest
{
    private ?MarketingSuggestion $s = null;

    public function suggestion(): ?MarketingSuggestion
    {
        return $this->s ??= MarketingSuggestion::query()->find((int) $this->route('id'));
    }

    protected function handler(): ?SuggestionType
    {
        return $this->suggestion() ? app(SuggestionTypeRegistry::class)->get($this->suggestion()->type) : null;
    }

    protected function targetType(): ?string
    {
        return $this->suggestion()?->target_entity_type;
    }

    protected function creating(): bool
    {
        return false;
    }
}
