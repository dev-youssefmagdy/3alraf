<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1 (MARKETER_PLAN.md): the in-panel AI module is removed. Drops every `ai_*` table, the `ai_*` permissions (and their role/user links)
 * and any `ai*` row in `settings`. IRREVERSIBLE: down() recreates nothing (the data is gone; restore from a backup if ever needed).
 */
return new class extends Migration
{
    /** Children first (FKs: messages → conversations → models → providers; settings/usage/generations → models). */
    private const TABLES = ['ai_messages', 'ai_conversations', 'ai_usage_logs', 'ai_generations', 'ai_trend_snapshots', 'ai_settings', 'ai_models', 'ai_providers'];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (self::TABLES as $t) {
            Schema::dropIfExists($t);
        }
        Schema::enableForeignKeyConstraints();

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->where('name', 'like', 'ai\_%')->pluck('id');
            if ($ids->isNotEmpty()) {
                foreach (['role_has_permissions', 'model_has_permissions'] as $pivot) {
                    Schema::hasTable($pivot) && DB::table($pivot)->whereIn('permission_id', $ids)->delete();
                }
                DB::table('permissions')->whereIn('id', $ids)->delete();
            }
        }
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'ai')->orWhere('key', 'like', 'ai\_%')->orWhere('key', 'like', 'ai.%')->delete();
        }
        app()->bound('cache') && rescue(fn () => app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions(), report: false);
    }

    public function down(): void
    {
        // Irreversible by design: the AI module and its data were removed.
    }
};
