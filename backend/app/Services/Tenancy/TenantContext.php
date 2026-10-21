<?php

namespace App\Services\Tenancy;

use App\Models\Tenant;
use Closure;

/** Runs code as a tenant (queue jobs / artisan) so BelongsToTenant scopes and tenant_id defaults apply; restores the previous context. */
class TenantContext
{
    public static function run(Tenant $tenant, Closure $fn): mixed
    {
        $had = app()->bound('currentTenant');
        $prev = $had ? app('currentTenant') : null;
        app()->instance('currentTenant', $tenant);
        try {
            return $fn();
        } finally {
            if ($had) {
                app()->instance('currentTenant', $prev);
            } else {
                app()->forgetInstance('currentTenant');
                app()->offsetUnset('currentTenant');
            }
        }
    }
}
