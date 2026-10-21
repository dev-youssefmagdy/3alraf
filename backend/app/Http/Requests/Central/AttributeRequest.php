<?php

namespace App\Http\Requests\Central;

use App\Enums\AttributeType;
use App\Http\Requests\Concerns\TranslatableRules;
use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttributeRequest extends FormRequest
{
    use TranslatableRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('attribute')?->id;
        $rules = $this->translatable('name') + [
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_-]+$/', Rule::unique('attributes', 'code')->ignore($id)],
            'type' => ['required', Rule::enum(AttributeType::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'values' => ['required', 'array', 'min:1'],
            'values.*.id' => ['nullable', 'integer'],
            'values.*.color_hex' => [Rule::requiredIf($this->input('type') === 'color'), 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
        // each value: default language required
        $default = Language::activeList()->firstWhere('is_default', true)?->code ?? Language::activeList()->first()?->code;
        foreach (Language::activeList() as $l) {
            $rules["values.*.value.{$l->code}"] = [$l->code === $default ? 'required' : 'nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
