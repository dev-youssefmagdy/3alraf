<?php

namespace App\Http\Requests\Marketing\Api;

use App\DTOs\Marketing\SocialMediaAssetData;
use App\Enums\Marketing\MediaAssetKind;
use App\Enums\Marketing\MediaAssetStatus;
use Illuminate\Validation\Rule;

/** GET /api/internal/marketing/media (+ products/{id}/social-media): private library metadata filters (P6). */
class MediaListRequest extends MarketingQueryRequest
{
    protected function extraRules(): array
    {
        return [
            'product_id' => ['sometimes', 'integer', 'min:1'],
            'kind' => ['sometimes', Rule::in(MediaAssetKind::values())],
            'status' => ['sometimes', Rule::in(MediaAssetStatus::values())],
            'platform' => ['sometimes', Rule::in(SocialMediaAssetData::PLATFORMS)],
            'campaign' => ['sometimes', 'string', 'max:120'],
            'tag' => ['sometimes', 'string', 'max:60'],
            'search' => ['sometimes', 'string', 'max:100'],
            'sort' => ['sometimes', Rule::in(['newest', 'oldest'])],
        ];
    }

    public function filtersArray(): array
    {
        return $this->filters();
    }
}
