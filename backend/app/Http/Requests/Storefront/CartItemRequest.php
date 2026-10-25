<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class CartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer'],
            'bundle_id' => ['nullable', 'integer', 'min:0'],
            'variant_id' => ['nullable', 'integer', 'min:0'],
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
