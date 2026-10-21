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
            'email' => ['required', 'email', 'max:190', $unique],
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
