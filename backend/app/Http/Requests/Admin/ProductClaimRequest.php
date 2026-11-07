<?php

namespace App\Http\Requests\Admin;

use App\Services\Growth\BrandMemoryService;
use Illuminate\Validation\Rule;

class ProductClaimRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'type' => ['required', Rule::in(BrandMemoryService::CLAIM_TYPES)],
            'claim' => ['required', 'string', 'max:300'],
        ];
    }

    public function attributes(): array
    {
        return trans('growth_hub.brand.fields');
    }
}
