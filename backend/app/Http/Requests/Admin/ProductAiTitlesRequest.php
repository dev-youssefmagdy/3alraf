<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** POST admin/products/{product}/ai/titles (products.edit, throttle product-ai): title ideas for the product or one of its variants. */
class ProductAiTitlesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_type' => ['required', 'in:product,variant'],
            'target_id' => ['required_if:target_type,variant', 'nullable', 'integer'],
            'use_competitor_titles' => ['nullable', 'boolean'],
            'competitor_titles' => ['nullable', 'array', 'max:10'],
            'competitor_titles.*' => ['string', 'max:300'],
        ];
    }
}
