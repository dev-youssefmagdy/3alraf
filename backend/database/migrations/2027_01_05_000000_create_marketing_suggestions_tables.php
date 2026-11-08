<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P5 (MARKETER_PLAN.md, MCP.md §16/§23/§26): Suggestions Center + application engine.
 * - marketing_suggestions (+ _revisions, _approvals, _executions, _activity_logs): one row per suggestion, typed payload (validated per type),
 *   content hash + version, approval bound to (version, content_hash), idempotent executions with before/after, full activity trail.
 * - marketing_themes: versioned seasonal storefront palettes (CSS-variable overrides; at most one active).
 * - seo_redirects: 301s for changed slugs (served by App\Http\Middleware\SeoRedirects on storefront 404s).
 * - seo_metas: meta title / description for entities without their own meta columns (category, brand, bundle, static page, custom page, blog title).
 * Table names never start with `ai_` (2027_01_01_000000_drop_ai_module drops those on a fresh install).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_suggestions', function (Blueprint $t) {
            $t->id();
            $t->string('type', 30);
            $t->string('title', 200);
            $t->text('description')->nullable();
            $t->string('target_entity_type', 30)->nullable();
            $t->unsignedBigInteger('target_entity_id')->nullable();
            $t->json('payload');
            $t->json('source_data_snapshot')->nullable();
            $t->json('target_snapshot')->nullable();
            $t->char('target_snapshot_hash', 64)->nullable();
            $t->text('reasoning')->nullable();
            $t->string('priority', 10)->default('normal');
            $t->string('status', 20)->default('pending');
            $t->string('source', 12)->default('mcp');
            $t->json('source_meta')->nullable();
            $t->foreignId('created_by_token_id')->nullable()->constrained('marketing_api_tokens')->nullOnDelete();
            $t->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->foreignId('applied_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->char('content_hash', 64);
            $t->string('result_entity_type', 30)->nullable();
            $t->unsignedBigInteger('result_entity_id')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('applied_at')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->timestamp('rolled_back_at')->nullable();
            $t->timestamps();
            $t->index(['type', 'status'], 'mkt_sugg_type_status_idx');
            $t->index('status', 'mkt_sugg_status_idx');
            $t->index(['target_entity_type', 'target_entity_id'], 'mkt_sugg_target_idx');
            $t->index(['type', 'content_hash'], 'mkt_sugg_type_hash_idx');
            $t->index('created_at', 'mkt_sugg_created_idx');
        });

        Schema::create('marketing_suggestion_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('suggestion_id')->constrained('marketing_suggestions')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->string('title', 200);
            $t->json('payload');
            $t->char('content_hash', 64);
            $t->string('editor_type', 10);           // admin | token | system
            $t->unsignedBigInteger('editor_id')->nullable();
            $t->json('diff')->nullable();             // [{field, from, to}]
            $t->string('note', 500)->nullable();
            $t->timestamp('created_at')->nullable();
            $t->unique(['suggestion_id', 'version'], 'mkt_sugg_rev_version_uq');
        });

        Schema::create('marketing_suggestion_approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('suggestion_id')->constrained('marketing_suggestions')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->char('content_hash', 64);
            $t->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->string('decision', 10);               // approved | rejected
            $t->string('note', 1000)->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('invalidated_at')->nullable();
            $t->string('invalidated_reason', 100)->nullable();
            $t->timestamps();
            $t->index(['suggestion_id', 'decision'], 'mkt_sugg_appr_decision_idx');
        });

        Schema::create('marketing_suggestion_executions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('suggestion_id')->constrained('marketing_suggestions')->cascadeOnDelete();
            $t->string('kind', 12)->default('apply'); // apply | rollback | publish | schedule
            $t->string('idempotency_key', 100)->unique();
            $t->string('status', 12);                 // running | succeeded | failed
            $t->unsignedInteger('version');
            $t->char('content_hash', 64);
            $t->json('options')->nullable();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->json('warnings')->nullable();
            $t->text('error')->nullable();
            $t->foreignId('executed_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->foreignId('rollback_of')->nullable()->constrained('marketing_suggestion_executions')->nullOnDelete();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
            $t->index(['suggestion_id', 'kind', 'status'], 'mkt_sugg_exec_kind_idx');
        });

        Schema::create('marketing_suggestion_activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('suggestion_id')->constrained('marketing_suggestions')->cascadeOnDelete();
            $t->string('action', 30);
            $t->string('actor_type', 10);             // admin | token | system
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->nullable()->index('mkt_sugg_log_created_idx');
            $t->index(['suggestion_id', 'created_at'], 'mkt_sugg_log_sugg_created_idx');
        });

        Schema::create('marketing_themes', function (Blueprint $t) {
            $t->id();
            $t->string('key', 60);                    // occasion / theme family, e.g. ramadan
            $t->json('name');
            $t->unsignedInteger('version');
            $t->json('tokens');                       // {"color-brand-600": "#123456", ...} (whitelisted CSS variables)
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(false)->index();
            $t->foreignId('suggestion_id')->nullable()->constrained('marketing_suggestions')->nullOnDelete();
            $t->foreignId('previous_theme_id')->nullable()->constrained('marketing_themes')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->foreignId('activated_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamp('activated_at')->nullable();
            $t->timestamp('deactivated_at')->nullable();
            $t->timestamps();
            $t->unique(['key', 'version'], 'mkt_theme_key_version_uq');
        });

        Schema::create('seo_redirects', function (Blueprint $t) {
            $t->id();
            $t->string('from_path', 255)->unique();   // without the locale prefix when `localized`
            $t->string('to_path', 255);
            $t->boolean('localized')->default(true);
            $t->string('entity_type', 30)->nullable();
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->unsignedSmallInteger('status_code')->default(301);
            $t->unsignedInteger('hits')->default(0);
            $t->timestamp('last_hit_at')->nullable();
            $t->foreignId('suggestion_id')->nullable()->constrained('marketing_suggestions')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamps();
            $t->index(['entity_type', 'entity_id'], 'seo_redirect_entity_idx');
        });

        Schema::create('seo_metas', function (Blueprint $t) {
            $t->id();
            $t->string('entity_type', 30);
            $t->unsignedBigInteger('entity_id');
            $t->json('meta_title')->nullable();
            $t->json('meta_description')->nullable();
            $t->foreignId('suggestion_id')->nullable()->constrained('marketing_suggestions')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamps();
            $t->unique(['entity_type', 'entity_id'], 'seo_meta_entity_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_metas');
        Schema::dropIfExists('seo_redirects');
        Schema::dropIfExists('marketing_themes');
        Schema::dropIfExists('marketing_suggestion_activity_logs');
        Schema::dropIfExists('marketing_suggestion_executions');
        Schema::dropIfExists('marketing_suggestion_approvals');
        Schema::dropIfExists('marketing_suggestion_revisions');
        Schema::dropIfExists('marketing_suggestions');
    }
};
