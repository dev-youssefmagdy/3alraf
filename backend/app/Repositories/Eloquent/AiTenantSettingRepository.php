<?php

namespace App\Repositories\Eloquent;

use App\Models\AiTenantSetting;
use App\Repositories\Contracts\AiTenantSettingRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class AiTenantSettingRepository extends BaseRepository implements AiTenantSettingRepositoryInterface
{
    protected string $model = AiTenantSetting::class;

    public function query(): Builder
    {
        return AiTenantSetting::withoutGlobalScopes();
    }

    public function forTenant(int $tenantId): AiTenantSetting
    {
        return $this->query()->firstOrNew(['tenant_id' => $tenantId]);
    }

    public function save(int $tenantId, array $data): AiTenantSetting
    {
        $row = AiTenantSetting::withoutGlobalScopes()->firstOrNew(['tenant_id' => $tenantId]);
        $row->tenant_id = $tenantId;
        $row->fill($data)->save();

        return $row;
    }
}
