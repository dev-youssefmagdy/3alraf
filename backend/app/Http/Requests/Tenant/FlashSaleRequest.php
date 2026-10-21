<?php

namespace App\Http\Requests\Tenant;

use App\Enums\FlashDiscountType;
use App\Services\Stock\StockItemCatalog;
use Illuminate\Validation\Rule;

class FlashSaleRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->translatable('name') + [
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item' => ['required', 'string', 'distinct', function ($a, $v, $fail) {
                if (! app(StockItemCatalog::class)->exists((string) $v)) {
                    $fail(__('ops.invalid_item'));
                }
            }],
            'items.*.discount_type' => ['required', Rule::in(FlashDiscountType::values())],
            'items.*.value' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'items.*.qty_limit' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function ($v) {
            foreach ($this->input('items', []) as $k => $r) {
                if (($r['discount_type'] ?? '') === 'percent' && (float) ($r['value'] ?? 0) > 100) {
                    $v->errors()->add("items.$k.value", __('orders.flash.percent_max'));
                }
            }
        }];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['items.*.item' => __('ops.fields.item'), 'items.*.value' => __('orders.fields.value'), 'items.*.qty_limit' => __('orders.fields.qty_limit')];
    }
}
