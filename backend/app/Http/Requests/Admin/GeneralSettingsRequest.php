<?php

namespace App\Http\Requests\Admin;

use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Settings → General: shop profile, currency, locales, timezone + the Gift Finder and customer-cancel switches + affiliate sales bonus. */
class GeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route middleware can:general_settings.edit
    }

    public function rules(): array
    {
        $codes = Language::active()->pluck('code')->all();

        return [
            'name' => ['required', 'string', 'max:150'],
            'country' => ['nullable', 'string', 'size:2'],
            'currency_code' => ['required', 'string', 'size:3'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'default_locale' => ['required', Rule::in($codes), 'in_array:locales.*'],
            'locales' => ['required', 'array', 'min:1'],
            'locales.*' => [Rule::in($codes)],
            'timezone' => ['required', 'timezone:all'],
            'gift_finder_enabled' => ['nullable', 'boolean'],
            'customer_cancel_enabled' => ['nullable', 'boolean'],
            // affiliate sales bonus (docs/CALCULATIONS.md §Affiliate sales bonus): saved when the threshold field is submitted; the launch date is read-only
            'affiliate_bonus_enabled' => ['nullable', 'boolean'],
            // threshold ≥ 1,000 so a typo cannot mint hundreds of withdrawable bonuses; 0 < amount < threshold (0 would burn sales into 0.00 bonuses)
            'affiliate_bonus_threshold' => ['sometimes', 'required', 'numeric', 'min:1000', 'max:999999999'],
            'affiliate_bonus_amount' => ['required_with:affiliate_bonus_threshold', 'numeric', 'gt:0', 'lt:affiliate_bonus_threshold'],
        ];
    }

    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
