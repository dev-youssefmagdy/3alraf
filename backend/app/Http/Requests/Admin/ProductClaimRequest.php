<?php

namespace App\Http\Requests\Tenant;

use App\Services\Growth\BrandMemoryService;
use Illuminate\Validation\Rule;

class ProductClaimRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:tenant_products,product_id,tenant_id,'.current_tenant()->id],
            'type' => ['required', Rule::in(BrandMemoryService::CLAIM_TYPES)],
            'claim' => ['required', 'string', 'max:300'],
        ];
    }

    public function attributes(): array
    {
        return trans('growth_hub.brand.fields');
    }
}
