<?php

namespace App\Http\Requests\Admin;

use App\Support\SystemSettings;
use Illuminate\Foundation\Http\FormRequest;

/** Settings → System: rules are generated from the SystemSettings schema. */
class SystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route middleware can:general_settings.edit
    }

    protected function prepareForValidation(): void
    {
        $bools = collect(SystemSettings::flat())->filter(fn ($d) => $d[0] === 'bool')->keys();
        $this->merge($bools->mapWithKeys(fn ($k) => [$k => $this->boolean($k)])->all());
    }

    public function rules(): array
    {
        $rules = [];
        foreach (SystemSettings::flat() as $name => [$type]) {
            $rules[$name] = match ($type) {
                'bool' => ['boolean'],
                'int' => ['required', 'integer', 'min:0', 'max:2147483647'],
                'float' => ['required', 'numeric', 'min:0', 'max:999999999'],
                default => ['nullable', 'string', 'max:2000'],
            };
        }
        $rules['app_url'] = ['required', 'url:http,https', 'max:255'];
        $rules['media_url'] = ['required', 'url:http,https', 'max:255'];
        $rules['whatsapp_service_url'] = ['required', 'url:http,https', 'max:255'];
        $rules['meta_graph_url'] = ['required', 'url:https', 'max:255'];
        $rules['reverb_scheme'] = ['required', 'in:http,https'];
        foreach (['media_disk', 'media_private_disk', 'app_fallback_locale', 'app_faker_locale', 'mobile_legacy_store_slug', 'meta_graph_version', 'meta_currency'] as $k) {
            $rules[$k] = ['required', 'string', 'max:100'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return trans('system_settings.fields');
    }
}
