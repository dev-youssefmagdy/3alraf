<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Affiliate;

class AffiliateRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function attributes(): array
    {
        return trans('admin.fields') + trans('growth.fields');
    }
}
