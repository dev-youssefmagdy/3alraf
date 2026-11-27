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
        // mail: SMTP (real sending) or log (development: messages go to the log file, nothing is sent)
        $rules['mail_mailer'] = ['required', 'in:smtp,log'];
        $rules['mail_scheme'] = ['nullable', 'in:smtp,smtps'];
        $rules['mail_host'] = ['required_if:mail_mailer,smtp', 'nullable', 'string', 'max:255'];
        $rules['mail_port'] = ['required', 'integer', 'min:1', 'max:65535'];
        $rules['mail_from_address'] = ['required', 'email', 'max:255'];
        $rules['mail_from_name'] = ['required', 'string', 'max:255'];
        // AI (FreeLLMAPI): the base URL may be empty (tools off); the key is a secret (blank keeps the stored one)
        $rules['freellm_base_url'] = ['nullable', 'url:http,https', 'max:255'];
        $rules['freellm_text_models'] = ['required', 'string', 'max:500'];
        $rules['freellm_vision_models'] = ['required', 'string', 'max:500'];
        $rules['freellm_timeout'] = ['required', 'integer', 'min:5', 'max:120'];
        $rules['product_ai_search_backends'] = ['required', 'string', 'max:200', 'regex:/^\s*[a-z]+(\s*,\s*[a-z]+)*\s*$/'];
        $rules['product_ai_market_country'] = ['required', 'string', 'size:2', 'alpha'];
        $rules['product_ai_currency'] = ['required', 'string', 'size:3', 'alpha'];
        $rules['product_ai_marketplaces'] = ['nullable', 'string', 'max:2000'];
        $rules['product_ai_cache_hours'] = ['required', 'integer', 'min:0', 'max:720'];
        $rules['product_ai_throttle_per_minute'] = ['required', 'integer', 'min:1', 'max:120'];
        $rules['product_ai_max_page_fetches'] = ['required', 'integer', 'min:0', 'max:8'];
        foreach (['media_disk', 'media_private_disk', 'app_fallback_locale', 'app_faker_locale', 'mobile_legacy_store_slug', 'meta_graph_version', 'meta_currency'] as $k) {
            $rules[$k] = ['required', 'string', 'max:100'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return trans('admin.system_settings.fields');
    }
}
