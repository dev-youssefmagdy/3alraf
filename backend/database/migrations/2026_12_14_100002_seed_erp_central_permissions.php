<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/* erp_central.view|reports|audit must reach EXISTING installs (super-admin / tenant-owner are re-synced from config/permissions.php, idempotent). */
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
