<?php

namespace App\Http\Requests\Tenant;

class SeasonalEventRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'lead_time_days' => ['required', 'integer', 'between:0,365'], 'notes' => ['nullable', 'string', 'max:1000'],
            'category_ids' => ['nullable', 'array'], 'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }

    public function attributes(): array
    {
        return trans('growth_hub.seasonal.fields');
    }
}
