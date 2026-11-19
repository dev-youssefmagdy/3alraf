<?php

namespace App\Services\Tenancy;

use App\Models\Language;
use App\Models\Tenant;

/** Public list of ACTIVE tenants (central landing page + mobile app country picker). */
class TenantDirectory
{
    /** @return array<int,array{slug:string,name:string,country:?string,flag:string,currency:string,default_locale:string,locales:array,languages:array,base_url:string,logo_url:string}> */
    public function active(): array
    {
        $langs = Language::query()->active()->get()->keyBy('code');

        return Tenant::query()->where('is_active', true)->orderBy('name')->get()->map(fn (Tenant $t) => [
            'slug' => $t->slug,
            'name' => $t->name,
            'country' => $t->country,
            'flag' => country_flag($t->country),
            'currency' => $t->currency_code,
            'default_locale' => $t->default_locale,
            'locales' => array_values($t->locales ?? []),
            'languages' => collect($t->locales ?? [])->map(fn ($c) => $langs[$c]->native_name ?? $c)->values()->all(),
            'logo_url' => ($logo = trim((string) (($t->settings ?? [])['seo']['org_logo'] ?? ''))) !== '' ? (preg_match('#^https?://#i', $logo) ? $logo : url($logo)) : asset('logo.png'),
            'base_url' => $t->url('/'),
        ])->all();
    }

    public function findActive(string $slug): ?Tenant
    {
        return Tenant::query()->where('is_active', true)->where('slug', $slug)->first();
    }
}
