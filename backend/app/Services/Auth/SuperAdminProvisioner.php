<?php

namespace App\Services\Auth;

use App\Models\CentralAdmin;
use App\Models\TenantAdmin;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/** The only code path that can create a super admin (never public registration, never mass-assignment). One per scope, also enforced by a DB unique index. */
class SuperAdminProvisioner
{
    /** @param int|null $tenantId null = central */
    public function provision(?int $tenantId, string $name, string $phoneDigits, string $password): CentralAdmin|TenantAdmin
    {
        $existing = $this->query($tenantId)->where('is_super_admin', true)->first();
        if ($existing && $existing->phone !== $phoneDigits) {
            throw ValidationException::withMessages(['phone' => 'A super admin already exists for this scope.']);
        }
        $admin = $existing ?? ($this->query($tenantId)->where('phone', $phoneDigits)->first() ?? ($tenantId ? new TenantAdmin(['tenant_id' => $tenantId]) : new CentralAdmin));
        $admin->fill(['name' => $name, 'phone' => $phoneDigits, 'password' => $password, 'is_active' => true]);
        $admin->email ??= null;
        if ($tenantId) {
            $admin->tenant_id = $tenantId;
        }
        $admin->forceFill(['is_super_admin' => true])->save();
        if ($role = Role::where(['name' => $tenantId ? 'tenant-owner' : 'super-admin', 'guard_name' => $tenantId ? 'tenant' : 'central'])->first()) {
            $admin->assignRole($role);
        }

        return $admin;
    }

    private function query(?int $tenantId)
    {
        return $tenantId ? TenantAdmin::withoutGlobalScopes()->where('tenant_id', $tenantId) : CentralAdmin::query();
    }
}
