<?php

namespace App\Models\Concerns;

/**
 * Owner = tenant_id, or NULL = central. owner_key (tenant_id or 0) is derived on save, so a row can never carry a mismatched key.
 * Every chat query must go through scopeOwnedBy(): the tenant global scope does nothing on the central host.
 */
trait BelongsToWhatsappOwner
{
    use BelongsToTenant;

    public static function bootBelongsToWhatsappOwner(): void
    {
        static::saving(fn ($m) => $m->owner_key = (int) $m->tenant_id);
    }

    public function scopeOwnedBy($q, ?int $tenantId)
    {
        return $q->withoutGlobalScopes()->where($this->qualifyColumn('owner_key'), (int) $tenantId);
    }
}
