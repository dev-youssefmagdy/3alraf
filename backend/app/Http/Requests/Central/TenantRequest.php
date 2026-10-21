<?php

namespace App\Http\Requests\Central;

use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenant = $this->route('tenant');
        $codes = Language::active()->pluck('code')->all();
        $reserved = config('tenancy.reserved_subdomains', []);

        return [
            'slug' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', Rule::notIn($reserved),
                Rule::unique('tenants', 'slug')->ignore($tenant?->id)],
            'name' => ['required', 'string', 'max:150'],
            'country' => ['nullable', 'string', 'size:2'],
            'currency_code' => ['required', 'string', 'size:3'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'currency_rate' => ['required', 'numeric', 'gt:0'],
            'default_locale' => ['required', Rule::in($codes)],
            'locales' => ['required', 'array', 'min:1'],
            'locales.*' => [Rule::in($codes)],
            'timezone' => ['required', 'timezone:all'],
            'is_active' => ['nullable', 'boolean'],
            'admin_name' => ['nullable', 'string', 'max:150'],
            'admin_email' => $tenant ? ['nullable'] : ['nullable', 'email', 'max:190', 'required_with:admin_password'],
            'admin_password' => $tenant ? ['nullable'] : ['nullable', 'string', 'min:8', 'required_with:admin_email'],
        ];
    }

    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
