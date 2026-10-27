<?php

namespace App\Repositories\Eloquent;

use App\Models\IntegrationClient;
use App\Repositories\Contracts\IntegrationClientRepositoryInterface;

class IntegrationClientRepository extends BaseRepository implements IntegrationClientRepositoryInterface
{
    protected string $model = IntegrationClient::class;

    public function findByPrefix(string $prefix): ?IntegrationClient
    {
        return IntegrationClient::withoutGlobalScopes()->where('token_prefix', $prefix)->first();
    }

    public function prefixExists(string $prefix): bool
    {
        return IntegrationClient::withoutGlobalScopes()->where('token_prefix', $prefix)->exists();
    }

    public function webhookTargets(int $tenantId): \Illuminate\Support\Collection
    {
        return IntegrationClient::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('revoked_at')->whereNotNull('webhook_url')->get();
    }
}
