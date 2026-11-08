<?php

namespace App\Http\Requests\Admin;

class IntegrationClientRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'scopes' => ['required', 'array', 'min:1'], 'scopes.*' => ['string', 'in:'.implode(',', array_keys((array) config('integration.scopes')))]];
    }

    public function attributes(): array
    {
        return ['name' => __('admin.integration.name'), 'scopes' => __('admin.integration.scopes')];
    }
}
