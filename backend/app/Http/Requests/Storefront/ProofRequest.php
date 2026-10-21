<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class ProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120']];
    }
}
