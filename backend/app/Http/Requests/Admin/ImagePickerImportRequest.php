<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** POST admin/products/{product}/image-picker/import (products.edit). Only http(s) URLs; the download itself is SSRF-checked. */
class ImagePickerImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image_url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'page_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'title' => ['nullable', 'string', 'max:300'],
            'as' => ['required', 'in:gallery,main'],
            'variant_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
