<?php

namespace App\Http\Requests\Tenant;

use App\Services\Ai\GlobalMarketTrendService;
use App\Support\MarketCountries;
use Illuminate\Validation\Rule;

class AiMarketTrendRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'countries' => ['required', 'array', 'min:1', 'max:'.GlobalMarketTrendService::MAX_COUNTRIES],
            'countries.*' => ['string', Rule::in(MarketCountries::codes())],
            'period' => ['nullable', 'integer', Rule::in(GlobalMarketTrendService::PERIODS)],
            'keyword' => ['nullable', 'string', 'max:80'],
            'count' => ['nullable', 'integer', Rule::in(GlobalMarketTrendService::COUNTS)],
            'ai_model_id' => ['nullable', 'integer', Rule::in(app(GlobalMarketTrendService::class)->webModels()->pluck('id')->all())],
            'force' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return trans('ai.market.fields');
    }
}
