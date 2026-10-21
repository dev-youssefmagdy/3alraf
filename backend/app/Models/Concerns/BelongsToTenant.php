<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Global tenant scope + auto tenant_id on create. Central context (no tenant) sees all rows. */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);
        static::creating(function ($model) {
            if (empty($model->tenant_id) && $tenant = current_tenant()) {
                $model->tenant_id = $tenant->id;
            }
        });
    }

    public function initializeBelongsToTenant(): void
    {
        $this->mergeFillable(['tenant_id']);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant($q, int $tenantId)
    {
        return $q->withoutGlobalScope(TenantScope::class)->where($this->qualifyColumn('tenant_id'), $tenantId);
    }
}
