<?php

namespace App\Http\Requests\Tenant;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class BulkEnableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = ['rows' => ['required', 'array', function ($a, $v, $fail) {
            $on = collect($v)->filter(fn ($r) => filter_var($r['enabled'] ?? false, FILTER_VALIDATE_BOOL));
            if ($on->isEmpty()) {
                return $fail(__('admin.select_at_least_one'));
            }
            if (Product::whereIn('id', $on->keys())->where('is_active', true)->count() !== $on->count()) {
                $fail(__('admin.invalid_products'));
            }
        }]];
        foreach ($this->input('rows', []) as $productId => $row) {
            if (! filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOL)) {
                continue;
            }
            $rules["rows.$productId.price"] = ['required', 'numeric', 'min:0', 'max:999999999999'];
            $rules["rows.$productId.sale_price"] = ['nullable', 'numeric', 'min:0', "lt:rows.$productId.price"];
            $rules["rows.$productId.cost"] = ['required', 'numeric', 'min:0', 'max:999999999999'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
