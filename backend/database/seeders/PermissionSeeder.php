<?php

namespace Database\Seeders;

use App\Support\Marketing\MarketingPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent. Guard `admin`: every permission of config/permissions.php `admin`; system roles `super-admin` and `owner` get all of them.
 * Guard `marketer`: every permission of `marketer`; system role `marketer-owner` gets all of them, the preset role `marketer`
 * (MarketingPermissions::MARKETER) is created once; later edits in Roles are kept.
 */
class PermissionSeeder extends Seeder
{
    public const GUARD = 'admin';

    /** System roles (cannot be edited away in the UI): both hold every permission; super-admin also bypasses every gate. */
    public const ROLES = ['super-admin', 'owner'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = $this->permissions(self::GUARD);
        foreach (self::ROLES as $role) {
            Role::findOrCreate($role, self::GUARD)->syncPermissions($names);
        }

        // marketer guard only once its accounts table exists: earlier migrations (which call this seeder) run before the single-store
        // conversion and the AI-module drop, which would otherwise rewrite / strip these roles on a fresh install
        if (! Schema::hasTable('marketers')) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return;
        }
        $m = MarketingPermissions::GUARD;
        $mNames = $this->permissions($m);
        Role::findOrCreate(MarketingPermissions::OWNER_ROLE, $m)->syncPermissions($mNames);
        if (! Role::where('name', MarketingPermissions::ROLE)->where('guard_name', $m)->exists()) {
            Role::create(['name' => MarketingPermissions::ROLE, 'guard_name' => $m])
                ->syncPermissions(array_values(array_intersect(MarketingPermissions::MARKETER, $mNames)));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<string> */
    private function permissions(string $guard): array
    {
        $names = [];
        foreach (config('permissions.'.$guard, []) as $module => $actions) {
            foreach ($actions as $a) {
                $names[] = Permission::findOrCreate("$module.$a", $guard)->name;
            }
        }

        return $names;
    }
}
