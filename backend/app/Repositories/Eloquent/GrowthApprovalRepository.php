<?php

namespace App\Repositories\Eloquent;

use App\Models\GrowthApproval;
use App\Repositories\Contracts\GrowthApprovalRepositoryInterface;

class GrowthApprovalRepository extends BaseRepository implements GrowthApprovalRepositoryInterface
{
    protected string $model = GrowthApproval::class;

    public function lockForUpdate(int $id): GrowthApproval
    {
        return $this->query()->lockForUpdate()->findOrFail($id);
    }

    public function expireDue(iterable $tenantIds): int
    {
        return GrowthApproval::withoutGlobalScopes()->whereIn('tenant_id', collect($tenantIds))->where('status', GrowthApproval::PENDING)->where('expires_at', '<', now())
            ->update(['status' => GrowthApproval::EXPIRED, 'decided_at' => now()]);
    }

    public function markExpired(int $id): void
    {
        $this->query()->where('id', $id)->where('status', GrowthApproval::PENDING)->update(['status' => GrowthApproval::EXPIRED, 'decided_at' => now()]);
    }
}
