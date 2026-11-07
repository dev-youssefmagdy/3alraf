<?php

namespace App\Http\Requests\Tenant\Concerns;

use App\Services\Stock\StockItemCatalog;

trait HasStockItems
{
    protected function itemRules(bool $cost = false, bool $costRequired = false): array
    {
        $rules = [
            'items' => ['required', 'array', 'min:1'],
            'items.*.item' => ['required', 'string', 'distinct', function ($attr, $value, $fail) {
                if (! app(StockItemCatalog::class)->exists((string) $value)) {
                    $fail(__('ops.invalid_item'));
                }
            }],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000000'],
        ];
        if ($cost) {
            $rules['items.*.unit_cost'] = [$costRequired ? 'required' : 'nullable', 'numeric', 'min:0', 'max:999999999999'];
        }

        return $rules;
    }

    protected function itemAttributes(): array
    {
        return ['items' => __('ops.fields.items'), 'items.*.item' => __('ops.fields.item'), 'items.*.quantity' => __('ops.fields.quantity'), 'items.*.unit_cost' => __('ops.fields.unit_cost')];
    }

    public function stockItems(): array
    {
        return app(StockItemCatalog::class)->normalize($this->input('items', []));
    }
}
