<?php

namespace App\Http\Requests\Admin;

use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Settings → General: shop profile, currency, locales, timezone + the Gift Finder and customer-cancel switches. */
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
        ];
    }

    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
