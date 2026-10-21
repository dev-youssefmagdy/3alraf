<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Validation\Rule;

class StaticPageRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->translatable('title') + $this->translatable('content', false, 100000) + [
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('static_pages', 'slug')->where('tenant_id', current_tenant()->id)->ignore($this->id())],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + trans('growth.fields');
    }
}
