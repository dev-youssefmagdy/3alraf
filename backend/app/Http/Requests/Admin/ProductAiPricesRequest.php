<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** POST admin/products/{product}/ai/prices (products.edit, throttle product-ai): marketplace prices for a search text. */
class ProductAiPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:2', 'max:200'],
            'target_type' => ['nullable', 'in:product,variant'],
            'target_id' => ['nullable', 'integer'],
            'fresh' => ['nullable', 'boolean'],
        ];
    }
}
