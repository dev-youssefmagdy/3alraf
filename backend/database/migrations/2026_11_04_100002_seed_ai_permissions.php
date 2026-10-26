<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/*
 | New permissions (ai.*) must reach EXISTING installs: tenant-owner / super-admin are system roles synced from
 | config/permissions.php by PermissionSeeder (idempotent: findOrCreate + syncPermissions), so running it here is enough. Custom roles
 | are never touched. Re-run any time with: php artisan db:seed --class=PermissionSeeder
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            (new PermissionSeeder)->run();
        }
    }

    public function down(): void {}
};
