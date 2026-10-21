<?php

namespace App\Http\Requests\Central;

use App\Enums\TextDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission middleware on routes
    }

    public function rules(): array
    {
        $id = $this->route('language')?->id;

        return [
            'code' => ['required', 'string', 'max:10', 'regex:/^[a-zA-Z]{2,3}(-[a-zA-Z]{2})?$/', Rule::unique('languages', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:100'],
            'native_name' => ['required', 'string', 'max:100'],
            'direction' => ['required', Rule::enum(TextDirection::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
