<?php

namespace App\Http\Requests\Tenant;

use App\Services\Ai\AiAccess;
use App\Services\Ai\TrendService;
use Illuminate\Validation\Rule;

class AiTrendFilterRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'period' => ['nullable', 'integer', Rule::in(TrendService::PERIODS)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sort' => ['nullable', 'string', Rule::in(['qty', 'growth_pct', 'margin_pct', 'days_of_stock', 'revenue'])],
            'narrative' => ['nullable', 'boolean'],
            'ai_model_id' => ['nullable', 'integer', Rule::in(app(AiAccess::class)->modelsFor(current_tenant(), 'text')->pluck('id')->all())],
            'force' => ['nullable', 'boolean'],
            'tab' => ['nullable', Rule::in(['own', 'market'])],
        ];
    }

    public function attributes(): array
    {
        return trans('ai.fields');
    }
}
