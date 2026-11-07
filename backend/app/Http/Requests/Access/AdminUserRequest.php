<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id') ? (int) $this->route('id') : null;
        $unique = Rule::unique('admins', 'email')->ignore($id);

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190', $unique],
            'country' => ['required', Rule::in(array_keys(\App\Support\AdminPhone::countries()))],
            'phone' => ['required', 'string', 'max:30', function ($attr, $v, $fail) use ($id) {
                $d = \App\Support\AdminPhone::digits((string) $this->input('country'), $v);
                if (! $d) {
                    return $fail(__('validation.regex', ['attribute' => $attr]));
                }
                if (\App\Models\Admin::query()->where('phone', $d)->when($id, fn ($w) => $w->whereKeyNot($id))->exists()) {
                    $fail(__('validation.unique', ['attribute' => $attr]));
                }
            }],
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:8'],
            'is_active' => ['boolean'],
            'otp_exempt' => ['sometimes', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer'],
        ];
    }

    public function attributes(): array
    {
        return trans('access.fields');
    }
}
