<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Marketer panel split (ADMIN_REDESIGN_MARKETER_SPLIT_PLAN.md B2, owner decision Q4 = auto-migrate):
 *  1. marketing permissions + roles `marketer` / `marketer-owner` on guard `marketer` (PermissionSeeder);
 *  2. one `marketers` row per admin who could use the marketer panel or authored marketing data (same name / phone / password hash);
 *     super-admin / owner admins become `marketer-owner`, the others `marketer`;
 *  3. every marketing "who" column is re-pointed from `admins` to `marketers` (ids remapped, unknown ids → null),
 *     `created_by_admin_id` is renamed `created_by_marketer_id`, actor/editor type `admin` → `marketer`;
 *  4. the admin-guard marketing-only permissions are deleted (admins no longer reach /marketer); shared tool permissions
 *     (banners, discount_codes, flash_sales, bundles, blog_posts, static_pages, custom_pages) stay on both guards.
 * Back up the database before running on production. down() maps ids back through marketers.migrated_from_admin_id.
 */
return new class extends Migration
{
    /** table => [columns]; FK columns that pointed at admins.id. */
    private const FKS = [
        'marketing_api_tokens' => ['created_by'],
        'marketing_suggestions' => ['created_by_admin_id', 'reviewed_by', 'approved_by', 'applied_by'],
        'marketing_suggestion_approvals' => ['approved_by'],
        'marketing_suggestion_executions' => ['executed_by'],
        'marketing_themes' => ['created_by', 'activated_by'],
        'seo_redirects' => ['created_by'],
        'seo_metas' => ['updated_by'],
        'social_media_assets' => ['reviewed_by', 'created_by'],
        'social_media_asset_versions' => ['uploaded_by'],
        'meta_campaign_drafts' => ['created_by_admin_id', 'approved_by', 'published_by'],
        'meta_campaign_approvals' => ['requested_by', 'approved_by'],
    ];

    /** table => [type column, id column] of polymorphic-ish actor columns (type `admin` | `token` | `system`). */
    private const ACTORS = [
        'marketing_suggestion_revisions' => ['editor_type', 'editor_id'],
        'marketing_suggestion_activity_logs' => ['actor_type', 'actor_id'],
        'meta_audit_logs' => ['actor_type', 'actor_id'],
    ];

    private const RENAMES = ['marketing_suggestions', 'meta_campaign_drafts']; // created_by_admin_id → created_by_marketer_id

    public function up(): void
    {
        Schema::table('marketers', fn (Blueprint $t) => $t->unsignedBigInteger('migrated_from_admin_id')->nullable()->unique()->after('id'));

        (new PermissionSeeder)->run();
        // the preset role gets its full set here, whatever ran before (safety net for installs seeded by older code)
        $preset = \Spatie\Permission\Models\Role::where('name', \App\Support\Marketing\MarketingPermissions::ROLE)->where('guard_name', 'marketer')->first();
        $preset?->givePermissionTo(\Spatie\Permission\Models\Permission::where('guard_name', 'marketer')->whereIn('name', \App\Support\Marketing\MarketingPermissions::MARKETER)->get());
        // a role of the marketer panel never lives on the admin guard
        DB::table('roles')->where('guard_name', 'admin')->where('name', \App\Support\Marketing\MarketingPermissions::OWNER_ROLE)->delete();
        $map = $this->createMarketers();

        foreach (self::FKS as $table => $cols) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($cols as $col) {
                Schema::table($table, fn (Blueprint $t) => $t->dropForeign([$col]));
                $this->remap($table, $col, $map);
                $new = in_array($table, self::RENAMES, true) && $col === 'created_by_admin_id' ? 'created_by_marketer_id' : $col;
                Schema::table($table, function (Blueprint $t) use ($col, $new) {
                    if ($new !== $col) {
                        $t->renameColumn($col, $new);
                    }
                });
                Schema::table($table, fn (Blueprint $t) => $t->foreign($new)->references('id')->on('marketers')->nullOnDelete());
            }
        }
        foreach (self::ACTORS as $table => [$type, $id]) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            DB::table($table)->where($type, 'admin')->orderBy('id')->chunkById(500, function ($rows) use ($table, $type, $id, $map) {
                foreach ($rows as $r) {
                    DB::table($table)->where('id', $r->id)->update([$type => 'marketer', $id => $map[$r->{$id}] ?? null]);
                }
            });
        }

        // admins lose every marketing permission (direct + via roles); the old admin-guard `marketer` role goes too
        $names = $this->marketerPermissionNames();
        DB::table('permissions')->where('guard_name', 'admin')->whereIn('name', $names)->delete(); // pivots cascade
        DB::table('roles')->where('guard_name', 'admin')->where('name', 'marketer')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return array<int,int> admin id => marketer id */
    private function createMarketers(): array
    {
        $names = $this->marketerPermissionNames();
        $adminClass = \App\Models\Admin::class;
        $ids = collect();
        // admins holding any marketing permission directly or through a role (guard admin), incl. super-admin / owner roles
        $permIds = DB::table('permissions')->where('guard_name', 'admin')->whereIn('name', $names)->pluck('id');
        if ($permIds->isNotEmpty()) {
            $ids = $ids->merge(DB::table('model_has_permissions')->where('model_type', $adminClass)->whereIn('permission_id', $permIds)->pluck('model_id'));
            $roleIds = DB::table('role_has_permissions')->whereIn('permission_id', $permIds)->pluck('role_id');
            $ids = $ids->merge(DB::table('model_has_roles')->where('model_type', $adminClass)->whereIn('role_id', $roleIds)->pluck('model_id'));
        }
        // super admins (Gate::before gave them the marketer panel) and anyone who authored marketing data
        $ids = $ids->merge(DB::table('admins')->where('is_super_admin', true)->pluck('id'));
        foreach (self::FKS as $table => $cols) {
            if (Schema::hasTable($table)) {
                foreach ($cols as $col) {
                    $ids = $ids->merge(DB::table($table)->whereNotNull($col)->distinct()->pluck($col));
                }
            }
        }
        foreach (self::ACTORS as $table => [$type, $id]) {
            if (Schema::hasTable($table)) {
                $ids = $ids->merge(DB::table($table)->where($type, 'admin')->whereNotNull($id)->distinct()->pluck($id));
            }
        }

        $ownerRoleIds = DB::table('roles')->where('guard_name', 'admin')->whereIn('name', ['super-admin', 'owner'])->pluck('id');
        $mRoles = DB::table('roles')->where('guard_name', 'marketer')->pluck('id', 'name');
        $map = [];
        foreach (DB::table('admins')->whereIn('id', $ids->unique()->values())->get() as $a) {
            $existing = DB::table('marketers')->where('phone', $a->phone)->value('id');
            $email = $a->email && ! DB::table('marketers')->where('email', $a->email)->exists() ? $a->email : null;
            $mid = $existing ?: DB::table('marketers')->insertGetId([
                'migrated_from_admin_id' => $a->id, 'name' => $a->name, 'email' => $email, 'phone' => $a->phone, 'password' => $a->password,
                'is_active' => $a->is_active, 'otp_exempt' => $a->otp_exempt ?? false, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $map[$a->id] = $mid;
            $isOwner = $a->is_super_admin || DB::table('model_has_roles')->where('model_type', $adminClass)->where('model_id', $a->id)->whereIn('role_id', $ownerRoleIds)->exists();
            $role = $mRoles[$isOwner ? 'marketer-owner' : 'marketer'] ?? null;
            if ($role) {
                DB::table('model_has_roles')->insertOrIgnore(['role_id' => $role, 'model_type' => \App\Models\Marketer::class, 'model_id' => $mid]);
            }
        }

        return $map;
    }

    private function remap(string $table, string $col, array $map): void
    {
        DB::table($table)->whereNotNull($col)->whereNotIn($col, array_keys($map) ?: [0])->update([$col => null]);
        // two passes through a high offset (columns are unsigned) so a new id never collides with an old one still waiting to be remapped
        $off = 1_000_000_000_000;
        foreach ($map as $from => $to) {
            DB::table($table)->where($col, $from)->update([$col => $to + $off]);
        }
        DB::table($table)->where($col, '>=', $off)->update([$col => DB::raw("`$col` - $off")]);
    }

    /**
     * Permission names that exist only for the marketer panel. Shared marketing tools (banners, coupons, flash sales, bundles,
     * blog, pages) live on both guards and stay with the admins.
     *
     * @return list<string>
     */
    private function marketerPermissionNames(): array
    {
        $names = fn (string $guard) => collect(config('permissions.'.$guard, []))->flatMap(fn ($actions, $module) => array_map(fn ($a) => "$module.$a", $actions));

        return $names('marketer')->diff($names('admin'))->values()->all();
    }

    public function down(): void
    {
        $back = DB::table('marketers')->whereNotNull('migrated_from_admin_id')->pluck('migrated_from_admin_id', 'id')->all();
        foreach (self::FKS as $table => $cols) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($cols as $col) {
                $cur = in_array($table, self::RENAMES, true) && $col === 'created_by_admin_id' ? 'created_by_marketer_id' : $col;
                Schema::table($table, fn (Blueprint $t) => $t->dropForeign([$cur]));
                $this->remap($table, $cur, $back);
                Schema::table($table, function (Blueprint $t) use ($col, $cur) {
                    if ($cur !== $col) {
                        $t->renameColumn($cur, $col);
                    }
                });
                Schema::table($table, fn (Blueprint $t) => $t->foreign($col)->references('id')->on('admins')->nullOnDelete());
            }
        }
        foreach (self::ACTORS as $table => [$type, $id]) {
            if (Schema::hasTable($table)) {
                foreach (DB::table($table)->where($type, 'marketer')->get(['id', $id]) as $r) {
                    DB::table($table)->where('id', $r->id)->update([$type => 'admin', $id => $back[$r->{$id}] ?? null]);
                }
            }
        }
        // admin-guard marketing permissions come back with `php artisan db:seed --class=PermissionSeeder` after restoring config/permissions.php
        DB::table('model_has_roles')->where('model_type', \App\Models\Marketer::class)->delete();
        DB::table('marketers')->whereNotNull('migrated_from_admin_id')->delete();
        Schema::table('marketers', fn (Blueprint $t) => $t->dropColumn('migrated_from_admin_id'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
