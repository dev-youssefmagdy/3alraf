<?php

namespace App\Http\Requests\Marketing\Api;

use App\Services\Marketing\MarketingContentService;

/** GET seo-data: entity_type (product|category|brand|bundle|static_page|blog_post|site), optional id, missing_only. */
class SeoDataRequest extends MarketingQueryRequest
{
    protected function extraRules(): array
    {
        return [
            'entity_type' => ['sometimes', 'in:'.implode(',', MarketingContentService::SEO_TYPES)],
            'id' => ['sometimes', 'integer', 'min:1'],
            'missing_only' => ['sometimes', 'boolean'],
        ];
    }
}
