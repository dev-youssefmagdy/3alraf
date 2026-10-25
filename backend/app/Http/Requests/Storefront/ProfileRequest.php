<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('customers', 'email')->where('tenant_id', current_tenant()->id)->ignore(auth('web')->id())],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            // re-authenticate before changing the login credentials (stolen session / unlocked device)
            'current_password' => [Rule::requiredIf(fn () => $this->changesCredentials()), 'nullable', 'string', 'current_password:web'],
        ];
    }

    public function changesCredentials(): bool
    {
        $email = strtolower(trim((string) $this->input('email')));

        return $this->filled('password') || ($email !== '' && $email !== strtolower((string) auth('web')->user()?->email));
    }

    public function attributes(): array
    {
        return trans('admin.fields') + trans('growth.fields') + ['current_password' => __('store.current_password')];
    }
}
