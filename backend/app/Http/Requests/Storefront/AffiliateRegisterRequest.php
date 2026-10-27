<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Concerns\ValidatesTenantPhone;
use App\Models\Affiliate;

class AffiliateRegisterRequest extends FormRequest
{
    use ValidatesTenantPhone;

    public function after(): array
    {
        return [fn ($v) => $this->withPhoneValidator($v)];
    }

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhone();
        if ($this->has('email')) {
            $this->merge(['email' => Affiliate::normalizeEmail($this->input('email'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => Affiliate::emailRules($this->user('affiliate')?->id),
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
