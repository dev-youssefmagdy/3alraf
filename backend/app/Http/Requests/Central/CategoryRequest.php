<?php

namespace App\Http\Requests\Central;

use App\Http\Requests\Concerns\TranslatableRules;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    use TranslatableRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $current = $this->route('category');
        $forbidden = $current ? Category::query()->where('id', $current->id)->orWhere('path', 'like', $current->path.'/%')->pluck('id')->all() : [];

        return $this->translatable('name') + $this->translatable('description', false, 2000) + [
            'parent_id' => ['nullable', 'integer', 'exists:categories,id', Rule::notIn($forbidden)],
            'slug' => ['nullable', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')->ignore($current?->id)],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:3072'],
        ];
    }

    public function messages(): array
    {
        return ['parent_id.not_in' => __('admin.category_invalid_parent')];
    }

    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
