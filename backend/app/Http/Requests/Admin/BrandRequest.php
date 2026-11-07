<?php

namespace App\Http\Requests\Central;

use App\Http\Requests\Concerns\TranslatableRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    use TranslatableRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('brand')?->id;

        return $this->translatable('name') + [
            'slug' => ['nullable', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('brands', 'slug')->ignore($id)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function cleanName(): array
    {
        return $this->cleanTranslations($this->input('name'));
    }

    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
