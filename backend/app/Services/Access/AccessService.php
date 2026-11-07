<?php

namespace App\Services\Access;

use App\DTOs\Access\AdminUserData;
use App\DTOs\Access\RoleData;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Roles / staff accounts of the admin panel (guard `admin`; the `$guard` arguments are always `admin`). The seeded system roles
 * (super-admin, owner) are read-only; a system role is assignable only by its holders (super-admin may assign both).
 */
class AccessService
{
    public const GUARD = 'admin';

    /** Read-only seeded roles, most privileged first. */
    public const SYSTEM = ['super-admin', 'owner'];

    public function roleQuery(string $guard = self::GUARD): Builder
    {
        return Role::query()->where('guard_name', self::GUARD);
    }

    public function displayName(string $guard, Role $role): string
    {
        return $role->name;
    }

    public function isSystem(string $guard, Role $role): bool
    {
        return in_array($role->name, self::SYSTEM, true);
    }

    /** True when the acting admin may manage an account / role carrying the system role $name. */
    private function actorHolds(string $name): bool
    {
        $u = auth(self::GUARD)->user();

        return (bool) ($u?->hasRole('super-admin') || $u?->hasRole($name));
    }

    /** First system role held by $admin (null = none). */
    private function systemRoleOf(Admin $admin): ?string
    {
        foreach (self::SYSTEM as $r) {
            if ($admin->hasRole($r)) {
                return $r;
            }
        }

        return null;
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
        $own = auth(self::GUARD)->user()->getAllPermissions()->pluck('name')->all();
        $out = [];
        foreach (config('permissions.'.self::GUARD) as $module => $actions) {
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
        return Role::where('guard_name', self::GUARD)->where('name', trim($name))->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists();
    }

    public function saveRole(string $guard, RoleData $d, ?Role $role = null): Role
    {
        $allowed = collect($this->matrix($guard))->flatten()->all();
        $perms = array_values(array_intersect($d->permissions, $allowed));

        return DB::transaction(function () use ($guard, $d, $role, $perms, $allowed) {
            if ($role) {
                abort_if($this->isSystem($guard, $role), 403);
                $role->update(['name' => trim($d->name)]);
            } else {
                $role = Role::create(['name' => trim($d->name), 'guard_name' => self::GUARD]);
            }
            // keep permissions the editor cannot see/grant untouched
            $kept = $role->permissions->pluck('name')->diff($allowed)->all();
            $role->syncPermissions(Permission::where('guard_name', self::GUARD)->whereIn('name', array_merge($perms, $kept))->get());
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

    public function adminQuery(string $guard = self::GUARD): Builder
    {
        return Admin::query();
    }

    /** Load a staff account for edit/update/delete; accounts holding the system role are off-limits to non-holders. */
    public function findAdmin(string $guard, int|string $id): Admin
    {
        $admin = $this->adminQuery()->findOrFail($id);
        abort_if(($r = $this->systemRoleOf($admin)) && ! $this->actorHolds($r), 403);

        return $admin;
    }

    /** The acting admin holds a system role (super-admin / owner). */
    public function actorIsSystem(string $guard = self::GUARD): bool
    {
        return (bool) auth(self::GUARD)->user()?->hasAnyRole(self::SYSTEM);
    }

    /** The system role only by its holders; any other role only when all its permissions are held by the actor. */
    public function canAssign(string $guard, Role $role): bool
    {
        if ($this->isSystem($guard, $role)) {
            return $this->actorHolds($role->name);
        }
        if ($this->actorIsSystem($guard)) {
            return true;
        }
        $own = auth(self::GUARD)->user()?->getAllPermissions()->pluck('name')->all() ?? [];

        return $role->permissions->pluck('name')->diff($own)->isEmpty();
    }

    /** Roles the actor may pick in the staff form (+ those the account already holds, kept as-is). @return array<int,string> */
    public function assignableRoles(string $guard, ?Admin $admin = null): array
    {
        $held = $admin?->exists ? $admin->roles->pluck('id')->all() : [];

        return $this->roleQuery($guard)->with('permissions')->orderBy('id')->get()
            ->filter(fn (Role $r) => in_array($r->id, $held, true) || $this->canAssign($guard, $r))
            ->mapWithKeys(fn (Role $r) => [$r->id => $this->displayName($guard, $r)])->all();
    }

    public function saveAdmin(string $guard, AdminUserData $d, ?Admin $admin = null): Admin
    {
        $attrs = ['name' => $d->name, 'email' => $d->email, 'phone' => $d->phone, 'is_active' => $d->isActive];
        abort_if($admin && $admin->isSuperAdmin() && ! $d->isActive, 422, 'The super admin cannot be deactivated.');
        // only a system-role holder (owner / super admin) may grant or revoke the OTP exemption; anyone else leaves it untouched
        if ($d->otpExempt !== null && $this->actorIsSystem($guard)) {
            $attrs['otp_exempt'] = $d->otpExempt;
        }
        if ($d->password) {
            $attrs['password'] = $d->password;
        }
        $roles = $this->roleQuery($guard)->with('permissions')->whereIn('id', $d->roleIds)->get();
        abort_if($admin && ($r = $this->systemRoleOf($admin)) && ! $this->actorHolds($r), 403);
        // privilege escalation guard: every newly granted role must be assignable by the actor (roles already held may be kept)
        $held = $admin ? $admin->roles->pluck('id')->all() : [];
        foreach ($roles as $role) {
            if (! in_array($role->id, $held, true) && ! $this->canAssign($guard, $role)) {
                throw ValidationException::withMessages(['roles' => __('access.role_not_assignable')]);
            }
        }

        return DB::transaction(function () use ($guard, $attrs, $admin, $roles) {
            if ($admin) {
                if ($admin->getKey() === auth(self::GUARD)->id()) {
                    $attrs['is_active'] = true;   // cannot lock yourself out
                }
                $admin->update($attrs);
            } else {
                $admin = $this->adminQuery()->create($attrs);
            }
            // never let an admin strip their own system role(s)
            if ($admin->getKey() === auth(self::GUARD)->id()) {
                foreach (self::SYSTEM as $sys) {
                    if ($admin->hasRole($sys) && ! $roles->contains('name', $sys)) {
                        $roles->push(Role::findByName($sys, self::GUARD));
                    }
                }
            }
            $admin->syncRoles($roles);
            $this->audit($guard, $admin, 'admin.saved', ['roles' => $roles->pluck('name')->all(), 'active' => (bool) $admin->is_active, 'otp_exempt' => (bool) $admin->otp_exempt]);

            return $admin;
        });
    }

    public function deleteAdmin(string $guard, Admin $admin): void
    {
        if ($admin->getKey() === auth(self::GUARD)->id()) {
            throw ValidationException::withMessages(['email' => __('access.cannot_delete_self')]);
        }
        abort_if($admin->isSuperAdmin(), 403, 'The super admin cannot be deleted.');
        abort_if(($r = $this->systemRoleOf($admin)) && ! $this->actorHolds($r), 403);
        $this->audit($guard, $admin, 'admin.deleted');
        $admin->delete();
    }

    /** Audit trail for roles / permissions / staff changes (who, what). */
    private function audit(string $guard, \Illuminate\Database\Eloquent\Model $subject, string $event, array $props = []): void
    {
        $activity = activity('access')->performedOn($subject)->event($event)->withProperties($props);
        if ($causer = auth(self::GUARD)->user()) {
            $activity->causedBy($causer);
        }
        $activity->log($event);
    }
}
