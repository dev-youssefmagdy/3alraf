<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Validation\Rule;

class AffiliateTierRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'min_sales' => ['required', 'numeric', 'min:0', 'max:999999999999', Rule::unique('affiliate_tiers', 'min_sales')->where('tenant_id', current_tenant()->id)->ignore($this->id())],
            'percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + trans('growth.fields');
    }
}
