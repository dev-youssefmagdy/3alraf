<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Validation\Rule;

class BlogPostRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->translatable('title') + $this->translatable('excerpt', false, 500) + $this->translatable('content', false, 100000) + $this->translatable('meta_description', false, 200) + [
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('blog_posts', 'slug')->where('tenant_id', current_tenant()->id)->ignore($this->id())],
            'is_active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + trans('growth.fields');
    }
}
