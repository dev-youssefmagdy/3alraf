<?php

namespace App\Services\Ai;

use App\Models\AiModel;
use App\Models\AiTenantSetting;
use App\Models\Tenant;

/** Who may use AI: tenant switch + feature toggle + allowed models. Cached per request/tenant. */
class AiAccess
{
    private array $cache = [];

    public function settings(?Tenant $tenant = null): ?AiTenantSetting
    {
        $tenant ??= current_tenant();
        if (! $tenant) {
            return null;
        }

        if (! array_key_exists($tenant->id, $this->cache)) {
            $this->cache[$tenant->id] = AiTenantSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        }

        return $this->cache[$tenant->id];
    }

    public function forget(): void
    {
        $this->cache = [];
    }

    public function enabled(?Tenant $t = null): bool
    {
        return (bool) $this->settings($t)?->ai_enabled;
    }

    public function feature(string $feature, ?Tenant $t = null): bool
    {
        return (bool) $this->settings($t)?->featureOn($feature);
    }

    /** Active models of a kind the tenant may use. */
    public function modelsFor(?Tenant $t = null, string $kind = 'text')
    {
        $s = $this->settings($t);
        $q = AiModel::active()->with('provider')->where('kind', $kind)->orderBy('ai_provider_id')->orderBy('label');
        if ($s?->allowed_model_ids !== null && $s) {
            $q->whereIn('id', $s->allowed_model_ids);
        }

        return $q->get();
    }
}
