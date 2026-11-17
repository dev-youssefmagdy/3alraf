<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * `ai_products.approve` (guard marketer) must reach EXISTING installs: approving / applying a new_product suggestion creates catalog data.
 * PermissionSeeder is idempotent: marketer-owner is re-synced to every marketer permission; the preset `marketer` role is left as edited.
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
