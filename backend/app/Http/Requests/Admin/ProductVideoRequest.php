<?php

namespace App\Http\Requests\Admin;

use App\Services\ProductVideoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Upload of one public gallery video. MIME is detected from the file content; the limit also honours PHP's own upload limits so the error is always clear. */
class ProductVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'video' => ['required', 'file', 'extensions:mp4,webm', 'mimetypes:'.implode(',', array_keys(config('media.product_video.mimes'))), 'max:'.ProductVideoService::maxKb()],
            'poster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        $tooLarge = __('admin.product_media.too_large', ['mb' => ProductVideoService::maxMb()]);
        $badType = __('admin.product_media.bad_type');

        return ['video.required' => __('admin.product_media.required'), 'video.uploaded' => $tooLarge, 'video.max' => $tooLarge, 'video.file' => $badType, 'video.extensions' => $badType, 'video.mimetypes' => $badType];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $max = (int) config('media.product_video.max_per_product');
            if ($this->route('product')->videos()->count() >= $max) {
                $v->errors()->add('video', __('admin.product_media.limit_reached', ['max' => $max]));
            }
        });
    }
}
