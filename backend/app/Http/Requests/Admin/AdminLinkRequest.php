<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class AdminLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['product_id' => ['required', 'integer', 'min:1'], 'channel' => ['nullable', 'string', 'max:20'], 'campaign' => ['nullable', 'string', 'max:60']];
    }
}
