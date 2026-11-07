<?php

namespace App\Http\Requests\Tenant;

class GrowthClientRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'scopes' => ['required', 'array', 'min:1'], 'scopes.*' => ['string', 'in:'.implode(',', array_keys(config('growth.scopes')))], 'webhook_url' => ['nullable', 'url', 'max:500']];
    }

    public function attributes(): array
    {
        return trans('growth_hub.fields');
    }
}
