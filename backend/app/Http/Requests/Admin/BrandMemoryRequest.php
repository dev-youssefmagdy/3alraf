<?php

namespace App\Http\Requests\Tenant;

use App\Services\Growth\BrandMemoryService;
use Illuminate\Validation\Rule;

class BrandMemoryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'kind' => [$this->isMethod('PUT') ? 'prohibited' : 'required', Rule::in(BrandMemoryService::KINDS)],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:4000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return trans('growth_hub.brand.fields');
    }
}
