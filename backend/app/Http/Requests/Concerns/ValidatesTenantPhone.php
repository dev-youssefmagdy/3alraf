<?php

namespace App\Http\Requests\Concerns;

use App\Support\Phone;
use Illuminate\Validation\Validator;

/** Normalises the typed national phone and forces the tenant's dial code (any client-sent code is ignored). */
trait ValidatesTenantPhone
{
    private ?string $phoneError = null;

    protected function normalizePhone(string $field = 'phone'): void
    {
        $this->phoneError = null;
        $raw = $this->input($field);
        if (! is_string($raw) || trim($raw) === '') {
            $this->merge(['phone_country_code' => null]);

            return;
        }
        $this->merge(['phone_country_code' => Phone::tenantCode()]);
        [$n, $err] = Phone::normalize($raw, current_tenant()?->country);
        if ($err) {
            $this->phoneError = $err;
        } else {
            $this->merge([$field => $n]);
        }
    }

    protected function phoneCodeRule(): array
    {
        return ['phone_country_code' => ['nullable', 'string', 'max:6']];
    }

    protected function withPhoneValidator(Validator $v, string $field = 'phone'): void
    {
        if ($this->phoneError) {
            $v->errors()->add($field, __('store.phone_'.$this->phoneError, ['code' => Phone::tenantCode()]));
        }
    }
}
