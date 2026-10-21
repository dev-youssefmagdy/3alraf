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
            \App\Models\PaymentMethod::ensureDefaultCod($tenant->id);
            $this->provisionDefaults($tenant);
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

    /** Defaults every new store needs to be operable: a default branch (stock/orders need one) and the affiliate commission tiers (0 / 1000 / 5000 -> 5% / 7% / 10%). */
    private function provisionDefaults(Tenant $tenant): void
    {
        \App\Models\Branch::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $tenant->id, 'is_default' => true],
            ['name' => ['ar' => 'الفرع الرئيسي', 'en' => 'Main branch'], 'is_active' => true]);
        foreach ([['Starter', 0, 5], ['Silver', 1000, 7], ['Gold', 5000, 10]] as [$name, $min, $pct]) {
            \App\Models\AffiliateTier::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $tenant->id, 'min_sales' => $min], ['name' => $name, 'percent' => $pct]);
        }
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
