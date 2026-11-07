<?php

namespace App\Http\Requests\Tenant;

use App\Http\Requests\Concerns\ValidatesTenantPhone;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Quick "new customer" form of the POS: name + phone, e-mail optional. A phone already on file is refused (pick that customer instead). */
class PosCustomerRequest extends FormRequest
{
    use ValidatesTenantPhone;

    protected function prepareForValidation(): void
    {
        $this->normalizePhone();
    }

    public function after(): array
    {
        return [
            fn ($v) => $this->withPhoneValidator($v),
            function ($v) {
                if ($v->errors()->has('phone') || ! $this->filled('phone')) {
                    return;
                }
                if (Customer::query()->where('phone', $this->input('phone'))->exists()) {
                    $v->errors()->add('phone', __('pos.customer_exists'));
                }
            },
        ];
    }

    public function authorize(): bool
    {
        return $this->user('tenant')?->can('pos.use') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            ...$this->phoneCodeRule(),
            'email' => ['nullable', 'email', 'max:190', Rule::unique('customers', 'email')->where('tenant_id', current_tenant()->id)],
        ];
    }

    public function attributes(): array
    {
        return trans('admin.fields') + trans('growth.fields');
    }
}
