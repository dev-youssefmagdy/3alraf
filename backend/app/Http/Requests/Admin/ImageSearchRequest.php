<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST admin/products/image-search (perm products.view via the route). The modal pre-checks size / type in the browser (same limits, from
 * config image_search) so the admin sees the error before uploading; this request validates again on the server.
 */
class ImageSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $mimes = (array) config('image_search.mimes');

        return [
            'image' => ['required', 'file', 'max:'.(int) config('image_search.max_upload_kb', 10240), 'mimes:'.implode(',', $mimes),
                'mimetypes:image/jpeg,image/png,image/webp,image/avif'],
            'type' => ['sometimes', 'string', 'in:all,products,bundles'],
        ];
    }

    public function messages(): array
    {
        $file = $this->file('image');
        $mb = $file ? number_format($file->getSize() / 1048576, 1) : '?';
        $ext = $file ? strtoupper((string) ($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '?')) : '?';

        return [
            'image.max' => __('admin.image_search.too_large', ['x' => $mb]),
            'image.mimes' => __('admin.image_search.wrong_type', ['ext' => $ext]),
            'image.mimetypes' => __('admin.image_search.wrong_type', ['ext' => $ext]),
            'image.uploaded' => __('admin.image_search.too_large', ['x' => $mb]),
        ];
    }

    public function attributes(): array
    {
        return ['image' => __('admin.image_search.title')];
    }
}
