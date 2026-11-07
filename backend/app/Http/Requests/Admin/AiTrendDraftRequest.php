<?php

namespace App\Http\Requests\Tenant;

class AiTrendDraftRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', $this->tenantExists('suppliers', true)],
            'branch_id' => ['required', 'integer', $this->tenantExists('branches', true)],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'exists:tenant_products,product_id,tenant_id,'.current_tenant()->id],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
