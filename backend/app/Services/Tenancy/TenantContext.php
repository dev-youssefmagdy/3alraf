<?php

namespace App\Services\Tenancy;

use App\Models\Tenant;
use Closure;

/** Runs code as a tenant (queue jobs / artisan) so BelongsToTenant scopes, tenant_id defaults and the tenant timezone (as in ResolveTenant) apply; restores the previous context. */
class TenantContext
{
    public static function run(Tenant $tenant, Closure $fn): mixed
    {
        $had = app()->bound('currentTenant');
        $prev = $had ? app('currentTenant') : null;
        $prevTz = date_default_timezone_get();
        app()->instance('currentTenant', $tenant);
        if ($tenant->timezone) {
            date_default_timezone_set($tenant->timezone);
        }
        try {
            return $fn();
        } finally {
            date_default_timezone_set($prevTz);
            if ($had) {
                app()->instance('currentTenant', $prev);
            } else {
                app()->forgetInstance('currentTenant');
                app()->offsetUnset('currentTenant');
            }
        }
    }
}
