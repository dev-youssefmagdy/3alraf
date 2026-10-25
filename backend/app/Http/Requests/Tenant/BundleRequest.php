<?php

namespace App\Http\Requests\Tenant;

use App\Services\Orders\PriceResolver;
use App\Services\Stock\StockItemCatalog;

class BundleRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->translatable('name') + $this->translatable('description', false, 5000) + [
            'price' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            // components must be listed (active) products/variants of THIS tenant (PriceResolver is tenant scoped)
            'items.*.item' => ['required', 'string', function ($a, $v, $fail) {
                $catalog = app(StockItemCatalog::class);
                if (! $catalog->exists((string) $v)) {
                    return $fail(__('ops.invalid_item'));
                }
                [$pid, $vid] = $catalog->split((string) $v);
                if (! app(PriceResolver::class)->item($pid, $vid)) {
                    $fail(__('orders.bundle.component_inactive'));
                }
            }],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['items.*.item' => __('ops.fields.item'), 'items.*.quantity' => __('orders.fields.qty'), 'price' => __('orders.bundle.price')];
    }
}
