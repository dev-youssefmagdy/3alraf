<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** POST admin/products/{product}/image-picker/search (products.edit, throttle image-picker). */
class ImagePickerSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['query' => ['required', 'string', 'max:200'], 'page' => ['nullable', 'integer', 'min:1', 'max:10']];
    }
}
