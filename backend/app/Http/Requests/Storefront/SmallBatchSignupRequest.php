<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class SmallBatchSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['contact' => ['required', 'string', 'max:120'], 'answer' => ['nullable', 'string', 'max:300'], 'quantity' => ['nullable', 'integer', 'min:1', 'max:20'], 'preorder_accepted' => ['nullable', 'boolean'], 'consent' => ['required', 'accepted']];
    }
}
