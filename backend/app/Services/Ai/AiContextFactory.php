<?php

namespace App\Services\Ai;

use App\Models\AiTenantSetting;
use App\Models\Tenant;

class AiContextFactory
{
    /** Tenant panel (or central panel with a switched tenant) -> country-aware context; null tenant -> platform context. */
    public function forTenant(?Tenant $tenant = null): AiContext
    {
        $tenant ??= current_tenant();
        if (! $tenant) {
            return new AiContext(null, null, null, null, null, [], null);
        }
        $s = AiTenantSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();

        return new AiContext($tenant->id, $tenant->name, $tenant->country, $tenant->currency_code, $tenant->default_locale,
            array_values((array) $tenant->locales), $tenant->timezone, $s?->brand_voice, array_values((array) ($s?->forbidden_words ?? [])));
    }
}
