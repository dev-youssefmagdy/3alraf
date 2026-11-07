<?php

namespace App\Http\Requests\Storefront;

use App\Http\Requests\Concerns\ValidatesShopPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockRequestRequest extends FormRequest
{
    use ValidatesShopPhone;

    protected function prepareForValidation(): void
    {
        $this->normalizePhone();
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)->whereNotNull('price')->whereNull('deleted_at')],
            'variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', (int) $this->input('product_id'))],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            ...$this->phoneCodeRule(),
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [fn ($v) => $this->withPhoneValidator($v)];
    }

    public function attributes(): array
    {
        return trans('store.request_fields');
    }
}
