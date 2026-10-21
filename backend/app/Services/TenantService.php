<?php

namespace App\Services;

use App\DTOs\TenantData;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class TenantService
{
    public function __construct(private TenantRepositoryInterface $tenants) {}

    public function create(TenantData $dto): Tenant
    {
        return DB::transaction(function () use ($dto) {
            $tenant = $this->tenants->create($dto->toArray());
            if ($dto->adminEmail && $dto->adminPassword) {
                $admin = TenantAdmin::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id, 'name' => $dto->adminName ?: $dto->name,
                    'email' => $dto->adminEmail, 'password' => $dto->adminPassword, 'is_active' => true,
                ]);
                if ($role = Role::where(['name' => 'tenant-owner', 'guard_name' => 'tenant'])->first()) {
                    $admin->assignRole($role);
                }
            }

            return $tenant;
        });
    }

    public function update(Tenant $tenant, TenantData $dto): Tenant
    {
        return $this->tenants->update($tenant, $dto->toArray());
    }

    public function delete(Tenant $tenant): void
    {
        $this->tenants->delete($tenant);
    }
}
