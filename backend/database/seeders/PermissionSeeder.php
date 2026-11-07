<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Idempotent: every permission of config/permissions.php on guard `admin`; system roles `super-admin` and `owner` get all of them. */
class PermissionSeeder extends Seeder
{
    public const GUARD = 'admin';

    /** System roles (cannot be edited away in the UI): both hold every permission; super-admin also bypasses every gate. */
    public const ROLES = ['super-admin', 'owner'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = [];
        foreach (config('permissions.'.self::GUARD, []) as $module => $actions) {
            foreach ($actions as $a) {
                $names[] = Permission::findOrCreate("$module.$a", self::GUARD)->name;
            }
        }
        foreach (self::ROLES as $role) {
            Role::findOrCreate($role, self::GUARD)->syncPermissions($names);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
