<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Catalog admin module was merged into Products. Everyone holding catalog.{view,create,edit,delete} (via a role or directly)
 * gets the matching products.* permission (union: nobody loses access), then the catalog.* permissions are deleted. Re-runnable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }
        $actions = ['view', 'create', 'edit', 'delete'];
        DB::transaction(function () use ($actions) {
            $now = now();
            foreach ($actions as $a) {
                $old = DB::table('permissions')->where('name', "catalog.$a")->get();
                if ($old->isEmpty()) {
                    continue;
                }
                foreach ($old->pluck('guard_name')->unique() as $guard) {
                    $new = DB::table('permissions')->where('name', "products.$a")->where('guard_name', $guard)->value('id');
                    $new ??= DB::table('permissions')->insertGetId(['name' => "products.$a", 'guard_name' => $guard, 'created_at' => $now, 'updated_at' => $now]);
                    $oldIds = $old->where('guard_name', $guard)->pluck('id');
                    DB::table('role_has_permissions')->whereIn('permission_id', $oldIds)->pluck('role_id')->unique()->each(function ($roleId) use ($new) {
                        DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $new, 'role_id' => $roleId]);
                    });
                    DB::table('model_has_permissions')->whereIn('permission_id', $oldIds)->get()->each(function ($row) use ($new) {
                        DB::table('model_has_permissions')->insertOrIgnore(['permission_id' => $new, 'model_type' => $row->model_type, 'model_id' => $row->model_id]);
                    });
                }
                DB::table('role_has_permissions')->whereIn('permission_id', $old->pluck('id'))->delete();
                DB::table('model_has_permissions')->whereIn('permission_id', $old->pluck('id'))->delete();
                DB::table('permissions')->whereIn('id', $old->pluck('id'))->delete();
            }
        });
        (new PermissionSeeder)->run(); // system roles re-synced, permission cache cleared
    }

    public function down(): void
    {
        throw new RuntimeException('Irreversible: catalog.* permissions were merged into products.*.');
    }
};
