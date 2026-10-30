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
        $central = RoleRequest::guardFor($this) === 'central';
        $id = $this->route('id') ? (int) $this->route('id') : null;
        $unique = $central
            ? Rule::unique('central_admins', 'email')->ignore($id)
            : Rule::unique('tenant_admins', 'email')->where('tenant_id', current_tenant()->id)->ignore($id);

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190', $unique],
            'country' => ['required', Rule::in(array_keys(\App\Support\AdminPhone::countries()))],
            'phone' => ['required', 'string', 'max:30', function ($attr, $v, $fail) use ($central, $id) {
                $d = \App\Support\AdminPhone::digits((string) $this->input('country'), $v);
                if (! $d) {
                    return $fail(__('validation.regex', ['attribute' => $attr]));
                }
                $q = $central ? \App\Models\CentralAdmin::query() : \App\Models\TenantAdmin::query(); // tenant scope applies
                if ($q->where('phone', $d)->when($id, fn ($w) => $w->whereKeyNot($id))->exists()) {
                    $fail(__('validation.unique', ['attribute' => $attr]));
                }
            }],
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:8'],
            'is_active' => ['boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer'],
        ];
    }

    public function attributes(): array
    {
        return trans('access.fields');
    }
}
