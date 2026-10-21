<?php

namespace App\Services;

use App\DTOs\Orders\CodFeeConfig;
use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;

/** Reads/writes the COD extra fee keys of tenants.settings (other settings keys are preserved). */
class CodFeeSettingsService
{
    public function __construct(private TenantRepositoryInterface $tenants) {}

    public function get(Tenant $tenant): CodFeeConfig
    {
        return CodFeeConfig::fromSettings($tenant->settings);
    }

    public function update(Tenant $tenant, CodFeeConfig $data): CodFeeConfig
    {
        $this->tenants->update($tenant, ['settings' => array_merge($tenant->settings ?? [], $data->toSettings())]);

        return $this->get($tenant);
    }
}
