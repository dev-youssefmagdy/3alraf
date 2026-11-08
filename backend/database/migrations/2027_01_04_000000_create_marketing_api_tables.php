<?php

use App\Support\Marketing\MarketingPermissions;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * P4 (MARKETER_PLAN.md): marketer panel + Marketing internal API.
 * - marketing_api_tokens: service tokens of /api/internal/marketing (MCP server). Only sha256(plain token) is stored; distinct from integration_clients (n8n).
 * - marketing_api_requests: call log (token, method, route name, status, duration). No body, no IP, no PII. Pruned by marketing:prune-requests.
 * - Permissions marketing.*, ai_suggestions.*, ai_media.*, ai_seo/ai_blog/ai_theme.approve, meta_ads.* (config/permissions.php) on guard `admin`,
 *   all of them to owner/super-admin, and the preset role `marketer` (created once). Idempotent: re-runs PermissionSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketing_api_tokens')) {
            Schema::create('marketing_api_tokens', function (Blueprint $t) {
                $t->id();
                $t->string('name', 100);
                $t->string('token_prefix', 32)->unique();
                $t->char('token_hash', 64);
                $t->json('scopes');
                $t->timestamp('last_used_at')->nullable();
                $t->timestamp('expires_at')->nullable();
                $t->timestamp('revoked_at')->nullable();
                $t->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('marketing_api_requests')) {
            Schema::create('marketing_api_requests', function (Blueprint $t) {
                $t->id();
                $t->foreignId('token_id')->nullable()->constrained('marketing_api_tokens')->nullOnDelete();
                $t->string('method', 8);
                $t->string('route', 120)->nullable();
                $t->unsignedSmallInteger('status');
                $t->unsignedInteger('duration_ms');
                $t->timestamp('created_at')->nullable()->index();
                $t->index(['token_id', 'created_at']);
            });
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            (new PermissionSeeder)->run();
            // On a fresh install earlier migrations already ran PermissionSeeder with this config (creating `marketer`) and
            // 2027_01_01_000000_drop_ai_module then deleted every `ai_*` permission: give the preset role its full set once, here.
            $role = Role::where('name', MarketingPermissions::ROLE)->where('guard_name', PermissionSeeder::GUARD)->first();
            $role?->givePermissionTo(Permission::where('guard_name', PermissionSeeder::GUARD)->whereIn('name', MarketingPermissions::MARKETER)->get());
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_api_requests');
        Schema::dropIfExists('marketing_api_tokens');
        // permissions / the marketer role are left in place (harmless; removing them would strip roles the owner may have edited)
    }
};
