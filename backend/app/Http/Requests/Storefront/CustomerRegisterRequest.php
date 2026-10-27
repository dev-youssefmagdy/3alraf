<?php

namespace App\Http\Requests\Storefront;

use App\Http\Requests\Concerns\ValidatesTenantPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRegisterRequest extends FormRequest
{
    use ValidatesTenantPhone;

    protected function prepareForValidation(): void
    {
        $this->normalizePhone();
    }

    public function after(): array
    {
        return [fn ($v) => $this->withPhoneValidator($v)];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('customers', 'email')->where('tenant_id', current_tenant()->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            ...$this->phoneCodeRule(),
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function attributes(): array
    {
        return trans('admin.fields') + trans('growth.fields');
    }
}
