<?php

namespace App\Http\Requests\Central;

use App\Enums\ProductType;
use App\Http\Requests\Concerns\TranslatableRules;
use App\Models\Language;
use App\Models\ProductVariant;
use App\Services\BarcodeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
{
    use TranslatableRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $variable = $this->input('type') === ProductType::VARIABLE->value;

        $rules = $this->translatable('name') + $this->translatable('description', false, 20000)
            + $this->translatable('meta_title', false, 255) + $this->translatable('meta_description', false, 500) + [
            'type' => ['required', Rule::enum(ProductType::class)],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'slug' => ['nullable', 'string', 'max:190', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($product?->id)],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product?->id)],
            'barcode' => ['nullable', 'digits:13', Rule::unique('products', 'barcode')->ignore($product?->id), Rule::unique('product_variants', 'barcode'),
                fn ($a, $v, $fail) => BarcodeService::isValidEan13((string) $v) ?: $fail(__('admin.invalid_ean13'))],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'is_active' => ['nullable', 'boolean'],
            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['image', 'max:4096'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
        ];

        if ($variable) {
            $rules += [
                'variants' => ['required', 'array', 'min:1'],
                'variants.*.id' => ['nullable', 'integer'],
                'variants.*.values' => ['required', 'array', 'min:1'],
                'variants.*.values.*' => ['integer', 'exists:attribute_values,id'],
                'variants.*.weight' => ['nullable', 'numeric', 'min:0'],
                'variants.*.is_active' => ['nullable', 'boolean'],
                'variants.*.image' => ['nullable', 'image', 'max:4096'],
            ];
            foreach (array_keys($this->input('variants', [])) as $key) {
                $id = $this->input("variants.$key.id");
                $rules["variants.$key.sku"] = ['required', 'string', 'max:100', Rule::unique('product_variants', 'sku')->ignore($id ?: null), Rule::unique('products', 'sku')->ignore($product?->id)];
            }
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($this->input('type') !== 'variable') {
                return;
            }
            $seen = [];
            $skus = [];
            foreach ($this->input('variants', []) as $key => $row) {
                $hash = ProductVariant::hashFor($row['values'] ?? []);
                if (isset($seen[$hash])) {
                    $v->errors()->add("variants.$key.values", __('admin.duplicate_variant'));
                }
                $seen[$hash] = true;
                $sku = strtolower(trim((string) ($row['sku'] ?? '')));
                if ($sku !== '' && isset($skus[$sku])) {
                    $v->errors()->add("variants.$key.sku", __('validation.distinct', ['attribute' => 'sku']));
                }
                $skus[$sku] = true;
            }
        }];
    }

    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
