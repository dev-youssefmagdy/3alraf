<?php

namespace App\Http\Requests\Tenant;

use App\Services\SeoSettingsService;

/** Tenant "SEO & Analytics" settings (AJAX-validated first, then saved). */
class SeoSettingsRequest extends BaseRequest
{
    public function rules(): array
    {
        $rules = [
            'indexable' => ['nullable', 'boolean'], 'track_staff' => ['nullable', 'boolean'],
            'home_title' => ['nullable', 'array'], 'home_title.*' => ['nullable', 'string', 'max:70'],
            'home_description' => ['nullable', 'array'], 'home_description.*' => ['nullable', 'string', 'max:170'],
            'title_suffix' => ['nullable', 'array'], 'title_suffix.*' => ['nullable', 'string', 'max:40'],
            'twitter_handle' => ['nullable', 'string', 'regex:/^@?[A-Za-z0-9_]{1,15}$/'],
            'org_phone' => ['nullable', 'string', 'regex:/^\+?[0-9\s\-()]{6,25}$/'], 'org_email' => ['nullable', 'email', 'max:120'],
            'org_type' => ['nullable', 'in:Organization,Store,LocalBusiness'],
            'org_street' => ['nullable', 'string', 'max:160'], 'org_city' => ['nullable', 'string', 'max:80'], 'org_region' => ['nullable', 'string', 'max:80'],
            'org_postal' => ['nullable', 'string', 'max:20'], 'org_country' => ['nullable', 'string', 'size:2', 'alpha'],
            'default_og_image' => ['nullable', 'url:https', 'max:500'], 'org_logo' => ['nullable', 'url:https', 'max:500'],
            'social' => ['nullable', 'array'],
            'app_links' => ['nullable', 'array'], 'app_links.ios' => ['nullable', 'url:https', 'max:500'], 'app_links.android' => ['nullable', 'url:https', 'max:500'],
        ];
        foreach (SeoSettingsService::ID_PATTERNS as $k => $re) {
            $rules[$k] = ['nullable', 'string', 'regex:'.$re];
        }
        foreach (SeoSettingsService::SOCIALS as $s) {
            $rules["social.$s"] = ['nullable', 'url:https', 'max:300'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        // tolerate pasted whitespace / meta tags: "<meta name=... content='abc'>" -> abc ; upper-case ids
        $in = [];
        foreach (array_keys(SeoSettingsService::ID_PATTERNS) as $k) {
            $v = trim((string) $this->input($k, ''));
            if (str_contains($k, 'verification') && preg_match('/content=["\']([^"\']+)["\']/', $v, $m)) {
                $v = $m[1];
            }
            if (in_array($k, ['ga4_id', 'gtm_id', 'google_ads_id', 'tiktok_pixel_id'], true)) {
                $v = strtoupper($v);
            }
            $in[$k] = $v === '' ? null : $v;
        }
        if ($this->filled('org_country')) {
            $in['org_country'] = strtoupper(trim((string) $this->input('org_country')));
        }
        $this->merge($in);
    }

    public function attributes(): array
    {
        return trans('seo.fields') + parent::attributes();
    }
}
