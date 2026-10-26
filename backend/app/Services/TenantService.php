<?php

namespace App\Services;

use App\DTOs\TenantData;
use App\Models\AffiliateTier;
use App\Models\Branch;
use App\Models\CentralAdmin;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Notifications\TenantNotification;
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
            PaymentMethod::ensureDefaultCod($tenant->id);
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

            DB::afterCommit(fn () => $this->notifyCentral($tenant, 'tenant_created'));

            return $tenant;
        });
    }

    /** Central admins allowed to view tenants get a bell + realtime notification (and the actor is skipped). */
    private function notifyCentral(Tenant $tenant, string $kind): void
    {
        $actor = auth('central')->id();
        CentralAdmin::where('is_active', true)->get()
            ->filter(fn ($a) => $a->getKey() !== $actor && $a->can('tenants.view'))
            ->each(fn ($a) => $a->notify(new TenantNotification($tenant, $kind)));
    }

    /** Defaults every new store needs to be operable: a default branch (stock/orders need one) and the affiliate commission tiers (0 / 1000 / 5000 -> 5% / 7% / 10%). */
    private function provisionDefaults(Tenant $tenant): void
    {
        Branch::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $tenant->id, 'is_default' => true],
            ['name' => ['ar' => 'الفرع الرئيسي', 'en' => 'Main branch'], 'is_active' => true]);
        foreach ([['Starter', 0, 5], ['Silver', 1000, 7], ['Gold', 5000, 10]] as [$name, $min, $pct]) {
            AffiliateTier::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $tenant->id, 'min_sales' => $min], ['name' => $name, 'percent' => $pct]);
        }
    }

    public function update(Tenant $tenant, TenantData $dto): Tenant
    {
        $was = (bool) $tenant->is_active;
        $tenant = $this->tenants->update($tenant, $dto->toArray());
        if ($was !== (bool) $tenant->is_active) {
            $this->notifyCentral($tenant, 'tenant_status');
        }

        return $tenant;
    }

    public function delete(Tenant $tenant): void
    {
        $this->tenants->delete($tenant);
    }
}
