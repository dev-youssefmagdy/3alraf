<?php

namespace App\Http\Requests\Tenant;

use App\Models\TenantProduct;
use App\Services\Ai\AiAccess;
use App\Services\Ai\ProductEditorService;
use Illuminate\Validation\Rule;

class AiProductEditRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1', 'max:'.ProductEditorService::MAX_BULK],
            'product_ids.*' => ['integer', 'exists:tenant_products,product_id,tenant_id,'.current_tenant()->id],   // only this tenant's listings
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => [Rule::in(TenantProduct::CONTENT_FIELDS)],
            'locales' => ['required', 'array', 'min:1'],
            'locales.*' => [Rule::in((array) current_tenant()->locales)],
            'preset' => ['required', Rule::in(ProductEditorService::PRESETS)],
            'instruction' => ['nullable', 'string', 'max:800', 'required_if:preset,custom'],
            'ai_model_id' => ['nullable', 'integer', Rule::in(app(AiAccess::class)->modelsFor(current_tenant(), 'text')->pluck('id')->all())],
        ];
    }
}
