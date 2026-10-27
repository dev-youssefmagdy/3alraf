<?php

namespace App\Http\Requests\Tenant;

use App\Services\Growth\ExperimentService;
use Illuminate\Validation\Rule;

class GrowthExperimentRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:150'], 'hypothesis' => ['nullable', 'string', 'max:2000'], 'objective' => ['nullable', 'string', 'max:300'], 'metric' => ['nullable', 'string', 'max:100'],
            'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'], 'budget_cap' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'status' => ['required', Rule::in(ExperimentService::STATUSES)], 'outcome' => ['nullable', Rule::in(ExperimentService::OUTCOMES)], 'learnings' => ['nullable', 'string', 'max:4000']];
    }

    public function attributes(): array
    {
        return trans('growth_hub.fields');
    }
}
