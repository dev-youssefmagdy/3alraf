<?php

namespace App\Repositories\Eloquent;

use App\Models\GrowthEvent;
use App\Repositories\Contracts\GrowthEventRepositoryInterface;

class GrowthEventRepository extends BaseRepository implements GrowthEventRepositoryInterface
{
    protected string $model = GrowthEvent::class;

    public function createForTenant(array $data): GrowthEvent
    {
        return GrowthEvent::withoutGlobalScopes()->create($data);
    }

    public function lockForUpdate(int $id): ?GrowthEvent
    {
        return GrowthEvent::withoutGlobalScopes()->lockForUpdate()->find($id);
    }

    public function dueIds(iterable $tenantIds, int $limit = 500): \Illuminate\Support\Collection
    {
        return GrowthEvent::withoutGlobalScopes()->whereIn('tenant_id', collect($tenantIds))->where('status', 'pending')->where('next_attempt_at', '<=', now())->orderBy('id')->limit($limit)->pluck('id');
    }

    public function prune(int $days): int
    {
        return GrowthEvent::withoutGlobalScopes()->whereIn('status', ['delivered', 'dead'])->where('created_at', '<', now()->subDays($days))->delete();
    }
}
