<?php

namespace Database\Seeders;

use App\Support\Marketing\MarketingPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent: every permission of config/permissions.php on guard `admin`; system roles `super-admin` and `owner` get all of them.
 * The preset role `marketer` (MarketingPermissions::MARKETER) is created once with its permissions; later edits in Roles are kept.
 */
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
        if (! Role::where('name', MarketingPermissions::ROLE)->where('guard_name', self::GUARD)->exists()) {
            Role::create(['name' => MarketingPermissions::ROLE, 'guard_name' => self::GUARD])
                ->syncPermissions(array_values(array_intersect(MarketingPermissions::MARKETER, $names)));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
