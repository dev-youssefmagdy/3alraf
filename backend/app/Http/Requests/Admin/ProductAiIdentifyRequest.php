<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** POST admin/products/{product}/ai/identify (products.edit, throttle product-ai): the photo is the product's / a variant's main photo or an upload. */
class ProductAiIdentifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'in:product,variant,upload'],
            'variant_id' => ['required_if:source,variant', 'nullable', 'integer'],
            'image' => ['required_if:source,upload', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.(int) config('product_ai.upload_max_kb', 5120)],
        ];
    }
}
