<?php

namespace App\Services\Access;

use App\DTOs\Access\AdminUserData;
use App\DTOs\Access\RoleData;
use App\Models\CentralAdmin;
use App\Models\TenantAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Roles / staff accounts for both admin guards. Spatie roles are global per guard, so tenant roles are stored as "t{tenantId}:{name}"
 * (isolation + uniqueness per tenant for free); the seeded system roles (super-admin, tenant-owner) are read-only and assignable.
 */
class AccessService
{
    public const SYSTEM = ['central' => 'super-admin', 'tenant' => 'tenant-owner'];

    private function prefix(string $guard): string
    {
        return $guard === 'tenant' ? 't'.current_tenant()->id.':' : '';
    }

    public function roleQuery(string $guard): Builder
    {
        $q = Role::query()->where('guard_name', $guard);
        if ($guard === 'tenant') {
            $p = str_replace(['%', '_'], ['\\%', '\\_'], $this->prefix($guard));
            $q->where(fn ($w) => $w->where('name', self::SYSTEM['tenant'])->orWhere('name', 'like', $p.'%'));
        }

        return $q;
    }

    public function displayName(string $guard, Role $role): string
    {
        return Str::startsWith($role->name, $this->prefix($guard)) ? Str::after($role->name, $this->prefix($guard)) : $role->name;
    }

    public function isSystem(string $guard, Role $role): bool
    {
        return $role->name === self::SYSTEM[$guard];
    }

    public function findRole(string $guard, int|string $id): Role
    {
        return $this->roleQuery($guard)->findOrFail($id);
    }

    /** @return array<int,array{id:int,name:string,system:bool,permissions:int,users:int}> */
    public function roles(string $guard): array
    {
        $users = DB::table('model_has_roles')->selectRaw('role_id, COUNT(*) c')->groupBy('role_id')->pluck('c', 'role_id');

        return $this->roleQuery($guard)->withCount('permissions')->orderBy('id')->get()->map(fn (Role $r) => [
            'id' => $r->id, 'name' => $this->displayName($guard, $r), 'system' => $this->isSystem($guard, $r),
            'permissions' => $r->permissions_count, 'users' => (int) ($users[$r->id] ?? 0),
        ])->all();
    }

    /** module => [action => permission name] for permissions the acting admin holds (cannot grant what you do not have). */
    public function matrix(string $guard): array
    {
        $own = auth($guard)->user()->getAllPermissions()->pluck('name')->all();
        $out = [];
        foreach (config("permissions.$guard") as $module => $actions) {
            foreach ($actions as $a) {
                if (in_array("$module.$a", $own, true)) {
                    $out[$module][$a] = "$module.$a";
                }
            }
        }

        return $out;
    }

    public function nameTaken(string $guard, string $name, ?int $ignoreId = null): bool
    {
        return Role::where('guard_name', $guard)->where('name', $this->prefix($guard).trim($name))->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists();
    }

    public function saveRole(string $guard, RoleData $d, ?Role $role = null): Role
    {
        $allowed = collect($this->matrix($guard))->flatten()->all();
        $perms = array_values(array_intersect($d->permissions, $allowed));

        return DB::transaction(function () use ($guard, $d, $role, $perms, $allowed) {
            if ($role) {
                abort_if($this->isSystem($guard, $role), 403);
                $role->update(['name' => $this->prefix($guard).trim($d->name)]);
            } else {
                $role = Role::create(['name' => $this->prefix($guard).trim($d->name), 'guard_name' => $guard]);
            }
            // keep permissions the editor cannot see/grant untouched
            $kept = $role->permissions->pluck('name')->diff($allowed)->all();
            $role->syncPermissions(Permission::where('guard_name', $guard)->whereIn('name', array_merge($perms, $kept))->get());

            return $role;
        });
    }

    public function deleteRole(string $guard, Role $role): void
    {
        abort_if($this->isSystem($guard, $role), 403);
        if (DB::table('model_has_roles')->where('role_id', $role->id)->exists()) {
            throw ValidationException::withMessages(['name' => __('access.role_in_use')]);
        }
        $role->delete();
    }

    // ---- staff accounts ----

    public function adminQuery(string $guard): Builder
    {
        return $guard === 'tenant' ? TenantAdmin::query() : CentralAdmin::query();
    }

    public function findAdmin(string $guard, int|string $id): CentralAdmin|TenantAdmin
    {
        return $this->adminQuery($guard)->findOrFail($id);
    }

    public function saveAdmin(string $guard, AdminUserData $d, CentralAdmin|TenantAdmin|null $admin = null): CentralAdmin|TenantAdmin
    {
        $attrs = ['name' => $d->name, 'email' => $d->email, 'is_active' => $d->isActive];
        if ($d->password) {
            $attrs['password'] = $d->password;
        }
        $roles = $this->roleQuery($guard)->whereIn('id', $d->roleIds)->get();

        return DB::transaction(function () use ($guard, $attrs, $admin, $roles) {
            if ($admin) {
                if ($admin->getKey() === auth($guard)->id()) {
                    $attrs['is_active'] = true;   // cannot lock yourself out
                }
                $admin->update($attrs);
            } else {
                $admin = $this->adminQuery($guard)->create($attrs);
            }
            // never let an admin strip their own system role
            if ($admin->getKey() === auth($guard)->id() && $admin->hasRole(self::SYSTEM[$guard]) && ! $roles->contains('name', self::SYSTEM[$guard])) {
                $roles->push(Role::findByName(self::SYSTEM[$guard], $guard));
            }
            $admin->syncRoles($roles);

            return $admin;
        });
    }

    public function deleteAdmin(string $guard, CentralAdmin|TenantAdmin $admin): void
    {
        if ($admin->getKey() === auth($guard)->id()) {
            throw ValidationException::withMessages(['email' => __('access.cannot_delete_self')]);
        }
        $admin->delete();
    }
}
