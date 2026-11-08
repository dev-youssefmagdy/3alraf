<?php

namespace App\Http\Requests\Marketing\Api;

use App\Enums\Marketing\MetaDraftStatus;
use Illuminate\Validation\Rule;

/** GET /api/internal/marketing/meta/* (scope meta:read): cursor paging of Meta lists, insights range / level, draft list filters (P6). */
class MetaReadRequest extends MarketingQueryRequest
{
    protected function extraRules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'after' => ['sometimes', 'string', 'max:200', 'regex:/^[A-Za-z0-9_\-=]+$/'],
            'campaign_id' => ['sometimes', 'string', 'regex:/^\d{1,30}$/'],
            'adset_id' => ['sometimes', 'string', 'regex:/^\d{1,30}$/'],
            'object_id' => ['sometimes', 'string', 'regex:/^\d{1,30}$/'],
            'level' => ['sometimes', Rule::in(['account', 'campaign', 'adset', 'ad'])],
            'date_preset' => ['sometimes', Rule::in((array) config('meta.insights_presets'))],
            'status' => ['sometimes', Rule::in(MetaDraftStatus::values())],
            'mine' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function filtersArray(): array
    {
        return $this->filters();
    }
}
