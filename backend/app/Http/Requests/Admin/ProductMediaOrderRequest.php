<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Full gallery order of one product as "image:ID" / "video:ID" keys; must list exactly the product's own media. */
class ProductMediaOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'string', 'distinct', 'regex:/^(image|video):\d+$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $product = $this->route('product');
            $own = array_merge($product->images()->pluck('id')->map(fn ($id) => "image:$id")->all(), $product->videos()->pluck('id')->map(fn ($id) => "video:$id")->all());
            $sent = array_values((array) $this->input('order'));
            sort($own);
            sort($sent);
            if ($own !== $sent) {
                $v->errors()->add('order', __('admin.product_media.order_stale'));
            }
        });
    }
}
