<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * P2 (MARKETER_PLAN.md, Decision 2): the n8n Growth system is removed; the Gift Finder and a read-only integration API stay.
 * - Drops every Growth table (list below). KEPT: gift_finder_sessions, integration_clients (minus webhook_url), integration_requests (now the API call log),
 *   orders.attribution / orders.channel / carts.attribution, orders.cancel_reason_code, order_returns.reason_code.
 * - Removes the growth.* permissions (and their role / user links), adds gift_finder.* + integration_clients.* to every role that had growth.view / growth.manage
 *   (system roles get them through PermissionSeeder), deletes the `growth` setting row, Growth notifications and queued Growth jobs.
 * IRREVERSIBLE: down() recreates nothing (the data is gone; restore from a backup if ever needed).
 */
return new class extends Migration
{
    /** Children before parents (FKs are disabled anyway while dropping). */
    public const TABLES = [
        'brand_memory_versions', 'brand_memory_entries', 'approved_product_claims', 'content_calendar', 'content_assets',
        'bundle_ab_variants', 'bundle_ab_tests', 'experiment_ad_days', 'growth_experiment_results', 'growth_experiments',
        'growth_action_log', 'growth_approvals', 'growth_brief_decisions', 'growth_daily_stats', 'growth_events',
        'interactions', 'missed_opportunities', 'reputation_alerts', 'improvement_tasks', 'ceo_challenges', 'trend_candidates',
        'seasonal_tasks', 'seasonal_memories', 'seasonal_events', 'small_batch_signups', 'small_batch_tests',
        'ambassador_rewards', 'ambassador_ideas', 'ambassadors', 'tracked_links', 'storefront_visits',
        'product_supply_terms', 'supplier_product_prices',
    ];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (self::TABLES as $t) {
            Schema::dropIfExists($t);
        }
        Schema::enableForeignKeyConstraints();

        if (Schema::hasColumn('integration_clients', 'webhook_url')) {
            Schema::table('integration_clients', fn ($t) => $t->dropColumn('webhook_url')); // the event outbox (webhooks) is gone
        }
        if (Schema::hasTable('integration_requests')) {
            DB::table('integration_requests')->delete(); // former idempotency records of write endpoints that no longer exist
        }

        if (Schema::hasTable('permissions')) {
            $growth = DB::table('permissions')->where('name', 'like', 'growth.%')->where('guard_name', 'admin')->pluck('id', 'name');
            // Custom roles that could see / manage Growth keep access to what survived (gift finder + integration clients).
            $roles = Schema::hasTable('role_has_permissions')
                ? DB::table('role_has_permissions')->whereIn('permission_id', $growth->only(['growth.view', 'growth.manage'])->values())->get(['role_id', 'permission_id'])
                : collect();
            if ($growth->isNotEmpty()) {
                foreach (['role_has_permissions', 'model_has_permissions'] as $pivot) {
                    Schema::hasTable($pivot) && DB::table($pivot)->whereIn('permission_id', $growth->values())->delete();
                }
                DB::table('permissions')->whereIn('id', $growth->values())->delete();
            }
            if (Schema::hasTable('roles')) {
                (new PermissionSeeder)->run(); // creates gift_finder.* / integration_clients.*, re-syncs the system roles
            }
            $new = DB::table('permissions')->where('guard_name', 'admin')->whereIn('name', ['gift_finder.view', 'gift_finder.manage', 'integration_clients.view', 'integration_clients.manage'])->pluck('id', 'name');
            $viewIds = $growth->only(['growth.view'])->values()->all();
            foreach ($roles as $r) {
                $grant = in_array($r->permission_id, $viewIds, true) ? ['gift_finder.view', 'integration_clients.view'] : ['gift_finder.view', 'gift_finder.manage', 'integration_clients.view', 'integration_clients.manage'];
                foreach ($grant as $name) {
                    isset($new[$name]) && DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $r->role_id, 'permission_id' => $new[$name]]);
                }
            }
        }
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'growth')->orWhere('key', 'like', 'growth.%')->orWhere('key', 'like', 'growth\_%')->delete();
        }
        if (Schema::hasTable('notifications')) {
            DB::table('notifications')->where('type', 'App\\Notifications\\GrowthNotification')->orWhere('data', 'like', '%"kind":"ad_cap_exceeded"%')->delete();
        }
        foreach (['jobs', 'failed_jobs'] as $q) {
            Schema::hasTable($q) && DB::table($q)->where('payload', 'like', '%DispatchGrowthEventJob%')->delete();
        }
        app()->bound('cache') && rescue(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions(), report: false);
    }

    public function down(): void
    {
        // Irreversible by design: the Growth system and its data were removed (P2).
    }
};
