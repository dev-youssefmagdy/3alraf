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
 * P6 (MARKETER_PLAN.md, MCP.md §7, §8, §21): private product social-media library + Meta campaign drafts.
 * - social_media_assets (+ _versions): internal marketing media per product on the PRIVATE disk (never public URLs, never in the
 *   storefront gallery / feeds / API v1 / sitemap), metadata, approval status, versions (every new file = new version row).
 * - meta_campaign_drafts: every MCP.md §7 draft field + version + content hash + Meta ids after "publish as PAUSED".
 * - meta_campaign_approvals: MCP.md §8 approval records bound to (draft version, content hash), expiry, idempotency key, execution result.
 * - meta_audit_logs: every draft / approval / Meta API action (never tokens).
 * - Permission ai_media.approve (library approvals), given to the preset role `marketer`.
 * Table names never start with `ai_` (2027_01_01_000000_drop_ai_module drops those on a fresh install); index names < 64 chars.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_media_assets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->string('kind', 10);                        // image | video | post (text-only post draft / brief, file optional)
            $t->string('title', 200);
            $t->json('caption')->nullable();               // {locale: text}
            $t->json('platforms')->nullable();             // [facebook, instagram, instagram_story, reels, tiktok, whatsapp, other]
            $t->string('format', 20)->nullable();          // post | story | reel | carousel | video | ad
            $t->string('aspect_ratio', 10)->nullable();    // 1:1 | 4:5 | 9:16 | 16:9 | other
            $t->string('purpose', 500)->nullable();        // creative purpose
            $t->string('campaign', 120)->nullable();
            $t->json('tags')->nullable();
            $t->json('hashtags')->nullable();
            $t->string('cta', 200)->nullable();
            $t->text('brief')->nullable();                 // media brief / storyboard / concept (no file yet)
            $t->timestamp('suggested_publish_at')->nullable();
            $t->string('canva_url', 500)->nullable();      // https://(www.)canva.com only
            $t->json('generation_meta')->nullable();       // AI generation metadata / prompt reference (untrusted text)
            // current file (copy of the latest version; null for a brief without a file)
            $t->string('disk', 30)->nullable();
            $t->string('path', 500)->nullable();
            $t->string('thumb_path', 500)->nullable();
            $t->string('original_name', 255)->nullable();
            $t->string('mime', 100)->nullable();
            $t->unsignedBigInteger('size_bytes')->nullable();
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->unsignedInteger('duration_seconds')->nullable();
            $t->char('checksum', 64)->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->string('status', 20)->default('draft');    // draft | pending_review | approved | rejected | archived
            $t->string('review_note', 1000)->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('source', 12)->default('upload');   // upload | suggestion
            $t->foreignId('suggestion_id')->nullable()->constrained('marketing_suggestions')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['product_id', 'status'], 'sma_product_status_idx');
            $t->index(['kind', 'status'], 'sma_kind_status_idx');
            $t->index('campaign', 'sma_campaign_idx');
        });

        Schema::create('social_media_asset_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained('social_media_assets')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->string('disk', 30);
            $t->string('path', 500);
            $t->string('thumb_path', 500)->nullable();
            $t->string('original_name', 255);
            $t->string('mime', 100);
            $t->unsignedBigInteger('size_bytes');
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->unsignedInteger('duration_seconds')->nullable();
            $t->char('checksum', 64);
            $t->string('note', 500)->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->unique(['asset_id', 'version'], 'smav_asset_version_uq');
        });

        Schema::create('meta_campaign_drafts', function (Blueprint $t) {
            $t->id();
            $t->string('name', 200);
            $t->string('objective', 40);
            $t->string('status', 20)->default('draft');   // draft | pending_approval | approved | rejected | publishing | published | failed | archived
            $t->json('product_ids')->nullable();
            $t->json('product_urls')->nullable();
            $t->json('audience')->nullable();              // {description, age_min, age_max, genders[], interests[]}
            $t->json('locations')->nullable();             // {countries[], cities[]}
            $t->json('placements')->nullable();            // {automatic, publisher_platforms[], positions[]}
            $t->string('budget_type', 10)->default('daily');
            $t->decimal('budget_amount', 14, 2);
            $t->string('currency', 3);
            $t->timestamp('start_at')->nullable();
            $t->timestamp('end_at')->nullable();
            $t->json('creative')->nullable();              // {asset_ids[], canva_url, notes}
            $t->json('copy')->nullable();                  // {primary_text, headline, description}
            $t->string('cta', 30)->nullable();
            $t->string('landing_url', 500)->nullable();
            $t->string('optimization_goal', 40)->nullable();
            $t->json('expected_kpis')->nullable();
            $t->text('risks')->nullable();
            $t->text('assumptions')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->char('content_hash', 64);
            $t->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $t->foreignId('created_by_token_id')->nullable()->constrained('marketing_api_tokens')->nullOnDelete();
            $t->timestamp('submitted_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->string('ad_account_id', 40)->nullable();   // the ad account used at publish time (without act_)
            $t->string('meta_campaign_id', 40)->nullable();
            $t->string('meta_adset_id', 40)->nullable();
            $t->string('meta_creative_id', 40)->nullable();
            $t->string('meta_ad_id', 40)->nullable();
            $t->string('meta_image_hash', 64)->nullable();
            $t->json('meta_statuses')->nullable();         // verified {campaign, adset, ad} statuses after publish
            $t->foreignId('published_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamp('published_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
            $t->index('status', 'mcd_status_idx');
        });

        Schema::create('meta_campaign_approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('draft_id')->constrained('meta_campaign_drafts')->cascadeOnDelete();
            $t->unsignedInteger('draft_version');
            $t->char('content_hash', 64);
            $t->foreignId('requested_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->foreignId('requested_by_token_id')->nullable()->constrained('marketing_api_tokens')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->string('approval_status', 20)->default('pending'); // pending | approved | rejected | invalidated
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->string('note', 1000)->nullable();
            $t->string('execution_status', 20)->nullable(); // running | succeeded | failed
            $t->string('idempotency_key', 100)->nullable();
            $t->json('external_reference')->nullable();     // Meta ids
            $t->text('execution_error')->nullable();
            $t->timestamp('invalidated_at')->nullable();
            $t->string('invalidated_reason', 40)->nullable();
            $t->timestamps();
            $t->unique('idempotency_key', 'mca_idempotency_uq');
            $t->index(['draft_id', 'approval_status'], 'mca_draft_status_idx');
        });

        Schema::create('meta_audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('draft_id')->nullable()->constrained('meta_campaign_drafts')->nullOnDelete();
            $t->string('action', 40);
            $t->string('actor_type', 10);                   // admin | token | system
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('endpoint', 200)->nullable();        // Graph path only (never query string / token)
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['draft_id', 'created_at'], 'mal_draft_created_idx');
            $t->index('action', 'mal_action_idx');
        });

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            (new PermissionSeeder)->run();   // creates ai_media.approve (config/permissions.php) for owner / super-admin
            $role = Role::where('name', MarketingPermissions::ROLE)->where('guard_name', PermissionSeeder::GUARD)->first();
            $role?->givePermissionTo(Permission::where('guard_name', PermissionSeeder::GUARD)->whereIn('name', MarketingPermissions::MARKETER)->get());
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_audit_logs');
        Schema::dropIfExists('meta_campaign_approvals');
        Schema::dropIfExists('meta_campaign_drafts');
        Schema::dropIfExists('social_media_asset_versions');
        Schema::dropIfExists('social_media_assets');
        // files on the private disk (social-media/…) and the ai_media.approve permission are left in place
    }
};
