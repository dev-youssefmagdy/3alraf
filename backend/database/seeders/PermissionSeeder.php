<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = ['central' => 'super-admin', 'tenant' => 'tenant-owner'];
        foreach (config('permissions') as $guard => $modules) {
            $names = [];
            foreach ($modules as $module => $actions) {
                foreach ($actions as $a) {
                    $names[] = Permission::findOrCreate("$module.$a", $guard)->name;
                }
            }
            Role::findOrCreate($roles[$guard], $guard)->syncPermissions($names);
        }
    }
}
