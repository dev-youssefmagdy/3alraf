<?php

namespace App\Http\Requests\Admin;

use App\Services\ProductAi\TitleSuggestionService;
use Illuminate\Foundation\Http\FormRequest;

/** POST admin/products/{product}/ai/titles/apply (products.edit, throttle product-ai): save one title into one locale of the name. */
class ProductAiApplyTitleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_type' => ['required', 'in:product,variant'],
            'target_id' => ['required_if:target_type,variant', 'nullable', 'integer'],
            'locale' => ['required', 'in:'.implode(',', (array) config('product_ai.locales', ['ar', 'en']))],
            'title' => ['required', 'string', 'min:'.TitleSuggestionService::MIN, 'max:'.TitleSuggestionService::MAX],
        ];
    }
}
