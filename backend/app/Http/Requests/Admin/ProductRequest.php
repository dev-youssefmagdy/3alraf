<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductType;
use App\Http\Requests\Concerns\DraftRules;
use App\Http\Requests\Concerns\TranslatableRules;
use App\Models\Language;
use App\Models\ProductVariant;
use App\Services\BarcodeService;
use App\Services\GiftFinder\GiftFinderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
{
    use DraftRules, TranslatableRules;

    protected function draftModel(): ?\Illuminate\Database\Eloquent\Model
    {
        return $this->route('product');
    }

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
            'weight' => ['nullable', 'integer', 'min:0', 'max:1000000'], // grams
            'is_active' => ['nullable', 'boolean'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999999999', 'required_with:sale_price,cost', 'required_if_accepted:is_active'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lte:price'], // equal = no discount shown (storefront strikes through only when lower)
            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'min_stock_alert_qty' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'gift_suitable' => ['nullable', 'boolean'],
            'gift_recipients' => ['nullable', 'array'], 'gift_recipients.*' => [Rule::in(GiftFinderService::RECIPIENTS)],
            'gift_occasions' => ['nullable', 'array'], 'gift_occasions.*' => [Rule::in(GiftFinderService::OCCASIONS)],
            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['image', 'max:15360'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
        ];

        if ($variable) {
            $rules += [
                'variants' => ['required', 'array', 'min:1'],
                'variants.*.id' => ['nullable', 'integer'],
                'variants.*.values' => ['required', 'array', 'min:1'],
                'variants.*.values.*' => ['integer', 'exists:attribute_values,id'],
                'variants.*.weight' => ['nullable', 'integer', 'min:0', 'max:1000000'], // grams
                'variants.*.is_active' => ['nullable', 'boolean'],
                'variants.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
                'variants.*.sale_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
                'variants.*.cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
                'variants.*.min_stock_alert_qty' => ['nullable', 'integer', 'min:0', 'max:1000000'],
                'variants.*.image' => ['nullable', 'image', 'max:15360'],
            ];
            foreach (array_keys($this->input('variants', [])) as $key) {
                $id = $this->input("variants.$key.id");
                $rules["variants.$key.sku"] = ['required', 'string', 'max:100', Rule::unique('product_variants', 'sku')->ignore($id ?: null), Rule::unique('products', 'sku')->ignore($product?->id)];
            }
        }

        // a draft needs only its name: SKUs (a placeholder is stored), categories, variants and price can come later
        $variantSkus = array_map(fn ($k) => "variants.$k.sku", array_keys((array) $this->input('variants', [])));

        return $this->relaxForDraft($rules, ['sku', 'category_ids', 'variants', 'price', ...$variantSkus]);
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
                $price = ($row['price'] ?? '') !== '' ? $row['price'] : $this->input('price');
                if (($row['sale_price'] ?? '') !== '' && is_numeric($price) && is_numeric($row['sale_price']) && bccomp((string) $row['sale_price'], (string) $price, 2) > 0) {
                    $v->errors()->add("variants.$key.sale_price", __('validation.lte.numeric', ['attribute' => __('admin.fields.sale_price'), 'value' => __('admin.fields.price')]));
                }
            }
        }];
    }

    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
