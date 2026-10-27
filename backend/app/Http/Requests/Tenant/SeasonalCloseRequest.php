<?php

namespace App\Http\Requests\Tenant;

class SeasonalCloseRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['content_formats' => ['nullable', 'string', 'max:1000'], 'start_timing' => ['nullable', 'string', 'max:1000'], 'objections' => ['nullable', 'string', 'max:1000'], 'lessons' => ['nullable', 'string', 'max:2000']];
    }

    public function attributes(): array
    {
        return trans('growth_hub.seasonal.fields');
    }
}
