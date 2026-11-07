<?php

namespace App\Http\Requests\Tenant;

use App\DTOs\Orders\CodFeeConfig;
use App\Models\City;
use Illuminate\Validation\Rule;

/** Tenant "COD fees" settings (AJAX-validated first, then saved). */
class CodFeeSettingsRequest extends BaseRequest
{
    public function rules(): array
    {
        $type = $this->input('cod_extra_fee_type');
        $needsFixed = in_array($type, ['fixed', 'fixed_plus_percent'], true);
        $needsPct = in_array($type, ['percent', 'fixed_plus_percent'], true);

        return $this->translatable('cod_extra_fee_label', false, 80) + [
            'cod_extra_fee_enabled' => ['nullable', 'boolean'],
            'cod_extra_fee_type' => ['required', Rule::in(CodFeeConfig::TYPES)],
            'cod_extra_fee_fixed' => [$needsFixed ? 'required' : 'nullable', 'numeric', 'min:0', 'max:999999'],
            'cod_extra_fee_percent' => [$needsPct ? 'required' : 'nullable', 'numeric', 'min:0', 'max:100'],
            'cod_extra_fee_min' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'cod_extra_fee_max' => ['nullable', 'numeric', 'min:0', 'max:999999'], // no gte:min — min > max is allowed, max wins (CALCULATIONS.md)
            'cod_extra_fee_free_over' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'cod_extra_fee_bearer' => ['required', Rule::in(['customer', 'merchant'])],
            'cod_extra_fee_city_fixed' => ['nullable', 'array', function ($attr, $value, $fail) {
                // only cities of THIS tenant (City is tenant-scoped)
                $ids = array_map('intval', array_keys((array) $value));
                if ($ids && City::whereIn('id', $ids)->count() !== count(array_unique($ids))) {
                    $fail(__('validation.exists', ['attribute' => $attr]));
                }
            }],
            'cod_extra_fee_city_fixed.*' => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ];
    }
}
