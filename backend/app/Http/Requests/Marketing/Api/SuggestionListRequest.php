<?php

namespace App\Http\Requests\Marketing\Api;

use App\Enums\Marketing\SuggestionStatus;
use App\Enums\Marketing\SuggestionTypeKey;
use Illuminate\Validation\Rule;

/** GET /api/internal/marketing/suggestions — filters (from/to on created_at, type, status, target, mine, search, sort). */
class SuggestionListRequest extends MarketingQueryRequest
{
    protected function extraRules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(SuggestionTypeKey::values())],
            'status' => ['sometimes', Rule::in(SuggestionStatus::values())],
            'target_entity_type' => ['sometimes', 'string', 'max:30'],
            'target_entity_id' => ['sometimes', 'integer', 'min:1'],
            'mine' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:100'],
            'sort' => ['sometimes', Rule::in(['newest', 'oldest', 'priority', 'updated'])],
        ];
    }

    public function filtersArray(): array
    {
        return $this->filters();
    }

    /** Dates are optional here: only applied when sent. */
    public function sentRange(): ?array
    {
        return $this->filled('from') ? [$this->toQuery()->from, $this->toQuery()->to] : null;
    }
}
