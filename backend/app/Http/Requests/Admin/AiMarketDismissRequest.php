<?php

namespace App\Http\Requests\Tenant;

class AiMarketDismissRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['key' => ['required', 'string', 'regex:/^[a-f0-9]{12}$/'], 'countries' => ['required', 'array', 'min:1', 'max:5'], 'countries.*' => ['string', 'size:2'],
            'period' => ['nullable', 'integer'], 'keyword' => ['nullable', 'string', 'max:80'], 'count' => ['nullable', 'integer']];
    }
}
