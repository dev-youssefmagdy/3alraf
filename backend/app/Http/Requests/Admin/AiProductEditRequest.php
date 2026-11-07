<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Services\Ai\AiAccess;
use App\Services\Ai\ProductEditorService;
use Illuminate\Validation\Rule;

class AiProductEditRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1', 'max:'.ProductEditorService::MAX_BULK],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => [Rule::in(Product::CONTENT_FIELDS)],
            'locales' => ['required', 'array', 'min:1'],
            'locales.*' => [Rule::in((array) setting('locales', []))],
            'preset' => ['required', Rule::in(ProductEditorService::PRESETS)],
            'instruction' => ['nullable', 'string', 'max:800', 'required_if:preset,custom'],
            'ai_model_id' => ['nullable', 'integer', Rule::in(app(AiAccess::class)->modelsFor('text')->pluck('id')->all())],
        ];
    }
}
