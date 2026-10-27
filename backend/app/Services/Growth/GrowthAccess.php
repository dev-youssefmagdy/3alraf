<?php

namespace App\Services\Growth;

use App\Models\Tenant;

/** Per-tenant `growth` feature flag: tenants.settings.growth.enabled, falling back to config('growth.enabled_default'). */
class GrowthAccess
{
    public function enabled(?Tenant $tenant = null): bool
    {
        $tenant ??= current_tenant();
        if (! $tenant || ! $tenant->is_active) {
            return false;
        }
        $flag = (($tenant->settings ?? [])['growth'] ?? [])['enabled'] ?? null;

        return $flag === null ? (bool) config('growth.enabled_default') : (bool) $flag;
    }

    public function set(Tenant $tenant, bool $on): void
    {
        $s = $tenant->settings ?? [];
        $s['growth']['enabled'] = $on;
        $tenant->forceFill(['settings' => $s])->save();
    }
}
