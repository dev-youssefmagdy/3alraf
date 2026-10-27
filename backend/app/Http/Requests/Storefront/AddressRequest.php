<?php

namespace App\Http\Requests\Storefront;

use App\Http\Requests\Concerns\ValidatesTenantPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddressRequest extends FormRequest
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
            'label' => ['nullable', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            ...$this->phoneCodeRule(),
            'city_id' => ['required', Rule::exists('cities', 'id')->where('tenant_id', current_tenant()->id)->whereNull('deleted_at')],
            'address' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
