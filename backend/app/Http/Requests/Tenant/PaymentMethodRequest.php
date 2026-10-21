<?php

namespace App\Http\Requests\Tenant;

use App\Enums\FeeBearer;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentProvider;
use Illuminate\Validation\Rule;

class PaymentMethodRequest extends BaseRequest
{
    public function rules(): array
    {
        $type = PaymentMethodType::tryFrom((string) $this->input('type'));
        $rules = $this->translatable('name') + $this->translatable('instructions', false, 2000) + [
            'type' => ['required', Rule::enum(PaymentMethodType::class)],
            'provider' => ['required', Rule::in($type ? array_keys(PaymentProvider::forType($type)) : [])],
            'account_number' => [$type === PaymentMethodType::WALLET ? 'required' : 'nullable', 'string', 'max:100'],
            'requires_proof' => ['nullable', 'boolean'],
            'fee_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'fee_fixed' => ['required', 'numeric', 'min:0', 'max:999999'],
            'fee_bearer' => ['required', Rule::enum(FeeBearer::class)],
            'is_sandbox' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'credentials' => ['nullable', 'array'],
        ];
        if ($type === PaymentMethodType::ONLINE && ($provider = PaymentProvider::tryFrom((string) $this->input('provider')))) {
            foreach ($provider->credentialFields() as $field => $required) {
                // on edit a blank secret means "keep the stored one"
                $rules["credentials.$field"] = [($required && ! $this->id()) ? 'required' : 'nullable', 'string', 'max:500'];
            }
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attrs = parent::attributes();
        foreach (PaymentProvider::cases() as $p) {
            foreach (array_keys($p->credentialFields()) as $f) {
                $attrs["credentials.$f"] = __('ops.credentials.'.$f);
            }
        }

        return $attrs;
    }
}
