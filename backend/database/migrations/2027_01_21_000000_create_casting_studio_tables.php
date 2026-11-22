<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** AI Casting Studio: additive only (new tables; product / media tables untouched). Files live on the PRIVATE disk. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_models', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('slug', 140)->unique();
            $t->string('gender', 20)->nullable();
            $t->string('age_range', 20)->nullable();
            $t->text('appearance')->nullable();
            $t->string('hairstyle', 200)->nullable();
            $t->string('clothing_style', 300)->nullable();
            $t->string('personality', 300)->nullable();
            $t->string('ad_role', 200)->nullable();
            $t->text('identity_prompt')->nullable();
            $t->unsignedBigInteger('identity_seed')->nullable();
            $t->string('identity_mode', 30)->default('prompt_seed');
            $t->string('status', 20)->default('draft')->index();
            $t->unsignedBigInteger('source_candidate_id')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('marketers')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('marketers')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('virtual_model_reference_images', function (Blueprint $t) {
            $t->id();
            $t->foreignId('virtual_model_id')->constrained('virtual_models')->cascadeOnDelete();
            $t->string('kind', 20)->default('reference');
            $t->string('source', 20)->default('generated');
            $t->string('disk', 30);
            $t->string('path', 255);
            $t->string('mime', 60)->nullable();
            $t->unsignedInteger('size_bytes')->default(0);
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->char('checksum', 64)->nullable();
            $t->boolean('is_primary')->default(false);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('marketers')->nullOnDelete();
            $t->timestamps();
            $t->index(['virtual_model_id', 'kind']);
        });

        Schema::create('casting_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->string('status', 20)->default('draft')->index();
            $t->string('provider', 30);
            $t->unsignedTinyInteger('requested_count')->default(4);
            $t->json('product_brief')->nullable();
            $t->json('audience')->nullable();
            $t->text('notes')->nullable();
            $t->text('error')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('marketers')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('casting_candidates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('casting_session_id')->constrained('casting_sessions')->cascadeOnDelete();
            $t->string('name', 120);
            $t->string('gender', 20)->nullable();
            $t->string('age_range', 20)->nullable();
            $t->text('appearance')->nullable();
            $t->string('hairstyle', 200)->nullable();
            $t->string('clothing', 300)->nullable();
            $t->string('personality', 300)->nullable();
            $t->string('ad_role', 200)->nullable();
            $t->json('suggested_poses')->nullable();
            $t->json('suggested_locations')->nullable();
            $t->text('rationale')->nullable();
            $t->text('prompt')->nullable();
            $t->unsignedBigInteger('seed')->nullable();
            $t->string('status', 20)->default('planned')->index();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->text('error')->nullable();
            $t->string('disk', 30)->nullable();
            $t->string('portrait_path', 255)->nullable();
            $t->string('job_token', 40)->nullable();
            $t->foreignId('virtual_model_id')->nullable()->constrained('virtual_models')->nullOnDelete();
            $t->json('generation_meta')->nullable();
            $t->timestamps();
        });

        Schema::create('model_generation_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('virtual_model_id')->nullable()->constrained('virtual_models')->nullOnDelete();
            $t->foreignId('casting_candidate_id')->nullable()->constrained('casting_candidates')->nullOnDelete();
            $t->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $t->string('kind', 20)->index();
            $t->string('provider', 30);
            $t->string('status', 20)->default('queued')->index();
            $t->text('prompt')->nullable();
            $t->unsignedBigInteger('seed')->nullable();
            $t->json('params')->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->string('job_token', 40)->nullable();
            $t->string('disk', 30)->nullable();
            $t->string('output_path', 255)->nullable();
            $t->unsignedBigInteger('social_media_asset_id')->nullable();
            $t->text('error')->nullable();
            $t->unsignedInteger('duration_ms')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('marketers')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('model_pose_presets', function (Blueprint $t) {
            $t->id();
            $t->string('type', 10);
            $t->string('key', 60);
            $t->json('label');
            $t->text('prompt_fragment');
            $t->boolean('is_system')->default(true);
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
            $t->unique(['type', 'key']);
        });

        Schema::create('model_usage_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('virtual_model_id')->constrained('virtual_models')->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $t->foreignId('generation_id')->nullable()->constrained('model_generation_history')->nullOnDelete();
            $t->string('usage', 20);
            $t->string('campaign', 120)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('marketers')->nullOnDelete();
            $t->timestamps();
        });

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            (new PermissionSeeder)->run();
        }
    }

    public function down(): void
    {
        foreach (['model_usage_history', 'model_pose_presets', 'model_generation_history', 'casting_candidates', 'casting_sessions', 'virtual_model_reference_images', 'virtual_models'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
