<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductListType;
use Illuminate\Validation\Rule;

class ProductListRequest extends BaseRequest
{
    public function rules(): array
    {
        abort_unless(ProductListType::tryFrom((string) $this->route('type')), 404);

        return [
            'limit' => ['required', 'integer', 'between:1,60'],
            'product_ids' => ['nullable', 'array', 'max:60'],
            'product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')->whereNull('deleted_at')],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + trans('growth.fields');
    }
}
