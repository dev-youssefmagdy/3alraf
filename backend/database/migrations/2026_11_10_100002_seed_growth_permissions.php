<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** growth.* permissions reach existing installs (system roles synced from config/permissions.php; custom roles untouched). */
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
