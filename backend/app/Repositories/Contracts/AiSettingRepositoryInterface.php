<?php

namespace App\Repositories\Contracts;

use App\Models\AiTenantSetting;

interface AiTenantSettingRepositoryInterface extends RepositoryInterface
{
    public function forTenant(int $tenantId): AiTenantSetting;

    public function save(int $tenantId, array $data): AiTenantSetting;
}
