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
            $this->audit($guard, $role, 'role.saved', ['permissions' => $role->permissions->pluck('name')->sort()->values()->all()]);

            return $role;
        });
    }

    public function deleteRole(string $guard, Role $role): void
    {
        abort_if($this->isSystem($guard, $role), 403);
        if (DB::table('model_has_roles')->where('role_id', $role->id)->exists()) {
            throw ValidationException::withMessages(['name' => __('access.role_in_use')]);
        }
        $this->audit($guard, $role, 'role.deleted');
        $role->delete();
    }

    // ---- staff accounts ----

    public function adminQuery(string $guard): Builder
    {
        return $guard === 'tenant' ? TenantAdmin::query() : CentralAdmin::query();
    }

    /** Load a staff account for edit/update/delete; accounts holding the system role are off-limits to non-holders. */
    public function findAdmin(string $guard, int|string $id): CentralAdmin|TenantAdmin
    {
        $admin = $this->adminQuery($guard)->findOrFail($id);
        abort_if($admin->hasRole(self::SYSTEM[$guard]) && ! $this->actorIsSystem($guard), 403);

        return $admin;
    }

    public function actorIsSystem(string $guard): bool
    {
        return (bool) auth($guard)->user()?->hasRole(self::SYSTEM[$guard]);
    }

    /** The system role only by its holders; any other role only when all its permissions are held by the actor. */
    public function canAssign(string $guard, Role $role): bool
    {
        if ($this->isSystem($guard, $role)) {
            return $this->actorIsSystem($guard);
        }
        if ($this->actorIsSystem($guard)) {
            return true;
        }
        $own = auth($guard)->user()?->getAllPermissions()->pluck('name')->all() ?? [];

        return $role->permissions->pluck('name')->diff($own)->isEmpty();
    }

    /** Roles the actor may pick in the staff form (+ those the account already holds, kept as-is). @return array<int,string> */
    public function assignableRoles(string $guard, CentralAdmin|TenantAdmin|null $admin = null): array
    {
        $held = $admin?->exists ? $admin->roles->pluck('id')->all() : [];

        return $this->roleQuery($guard)->with('permissions')->orderBy('id')->get()
            ->filter(fn (Role $r) => in_array($r->id, $held, true) || $this->canAssign($guard, $r))
            ->mapWithKeys(fn (Role $r) => [$r->id => $this->displayName($guard, $r)])->all();
    }

    public function saveAdmin(string $guard, AdminUserData $d, CentralAdmin|TenantAdmin|null $admin = null): CentralAdmin|TenantAdmin
    {
        $attrs = ['name' => $d->name, 'email' => $d->email, 'phone' => $d->phone, 'is_active' => $d->isActive];
        abort_if($admin && $admin->isSuperAdmin() && ! $d->isActive, 422, 'The super admin cannot be deactivated.');
        if ($d->password) {
            $attrs['password'] = $d->password;
        }
        $roles = $this->roleQuery($guard)->with('permissions')->whereIn('id', $d->roleIds)->get();
        abort_if($admin && $admin->hasRole(self::SYSTEM[$guard]) && ! $this->actorIsSystem($guard), 403);
        // privilege escalation guard: every newly granted role must be assignable by the actor (roles already held may be kept)
        $held = $admin ? $admin->roles->pluck('id')->all() : [];
        foreach ($roles as $role) {
            if (! in_array($role->id, $held, true) && ! $this->canAssign($guard, $role)) {
                throw ValidationException::withMessages(['roles' => __('access.role_not_assignable')]);
            }
        }

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
            $this->audit($guard, $admin, 'admin.saved', ['roles' => $roles->pluck('name')->all(), 'active' => (bool) $admin->is_active]);

            return $admin;
        });
    }

    public function deleteAdmin(string $guard, CentralAdmin|TenantAdmin $admin): void
    {
        if ($admin->getKey() === auth($guard)->id()) {
            throw ValidationException::withMessages(['email' => __('access.cannot_delete_self')]);
        }
        abort_if($admin->isSuperAdmin(), 403, 'The super admin cannot be deleted.');
        abort_if($admin->hasRole(self::SYSTEM[$guard]) && ! $this->actorIsSystem($guard), 403);
        $this->audit($guard, $admin, 'admin.deleted');
        $admin->delete();
    }

    /** Audit trail for roles / permissions / staff changes (who, what, which tenant). */
    private function audit(string $guard, \Illuminate\Database\Eloquent\Model $subject, string $event, array $props = []): void
    {
        $activity = activity('access')->performedOn($subject)->event($event)->withProperties($props);
        if ($causer = auth($guard)->user()) {
            $activity->causedBy($causer);
        }
        $activity->tap(fn ($a) => $a->tenant_id = $guard === 'tenant' ? current_tenant()?->id : null)->log($event);
    }
}
