<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/* New erp.* permissions must reach EXISTING installs: owner/super-admin roles are synced from config/permissions.php (idempotent). */
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
