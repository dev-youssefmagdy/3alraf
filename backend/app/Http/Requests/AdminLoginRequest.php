<?php

namespace App\Http\Requests;

use App\Support\AdminPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'country' => ['required', Rule::in(array_keys(AdminPhone::countries()))],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return trans('whatsapp.fields');
    }
}
