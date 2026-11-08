<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

/** Prepared gift cart (web + API v1): chosen suggested products (+ variant per variable product) and bundles of one Gift Finder session. */
class GiftFinderCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_ids' => ['nullable', 'array', 'max:50'], 'product_ids.*' => ['integer', 'min:1'],
            'variants' => ['nullable', 'array', 'max:50'], 'variants.*' => ['nullable', 'integer', 'min:0'],
            'bundle_ids' => ['nullable', 'array', 'max:50'], 'bundle_ids.*' => ['integer', 'min:1'],
        ];
    }
}
