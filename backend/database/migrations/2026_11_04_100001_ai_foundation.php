<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- central (no tenant_id) ----
        Schema::create('ai_providers', function (Blueprint $t) {
            $t->id();
            $t->string('driver', 30);
            $t->string('name', 120);
            $t->text('api_key')->nullable();          // encrypted cast; never serialised / audited
            $t->string('base_url', 255)->nullable();
            $t->boolean('is_active')->default(true);
            $t->text('settings')->nullable();         // encrypted:array
            $t->timestamp('last_tested_at')->nullable();
            $t->boolean('last_test_ok')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('ai_models', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ai_provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $t->string('model_id', 150);
            $t->string('label', 150);
            $t->string('kind', 10)->default('text');
            $t->json('capabilities')->nullable();    // tools, vision, web_search, json_mode
            $t->decimal('input_cost', 12, 4)->nullable();   // per 1M tokens
            $t->decimal('output_cost', 12, 4)->nullable();
            $t->unsignedInteger('max_output')->nullable();
            $t->boolean('is_active')->default(true);
            $t->boolean('is_default')->default(false);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['kind', 'is_active']);
        });

        Schema::create('ai_tenant_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $t->boolean('ai_enabled')->default(false);
            $t->unsignedBigInteger('monthly_token_quota')->nullable();   // null = unlimited
            $t->unsignedInteger('monthly_image_quota')->nullable();
            $t->unsignedInteger('monthly_video_quota')->nullable();
            $t->json('allowed_model_ids')->nullable();                   // null = all active
            $t->json('features')->nullable();                            // {chat:true,...}
            // tenant-owned preferences
            $t->foreignId('default_ai_model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $t->text('brand_voice')->nullable();
            $t->json('forbidden_words')->nullable();
            $t->json('default_platforms')->nullable();
            $t->timestamps();
        });

        Schema::create('ai_usage_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $t->string('user_type', 60)->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('feature', 30);
            $t->foreignId('ai_model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $t->unsignedInteger('tokens_in')->default(0);
            $t->unsignedInteger('tokens_out')->default(0);
            $t->unsignedInteger('images')->default(0);
            $t->unsignedInteger('video_secs')->default(0);
            $t->decimal('est_cost', 12, 6)->default(0);
            $t->unsignedInteger('latency_ms')->default(0);
            $t->string('status', 20)->default('ok');
            $t->string('country', 2)->nullable();
            $t->string('error', 255)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tenant_id', 'created_at']);
            $t->index(['feature', 'created_at']);
        });

        // ---- tenant scoped ----
        Schema::create('ai_conversations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('title', 190)->nullable();
            $t->foreignId('ai_model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $t->text('summary')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'user_id']);
        });

        Schema::create('ai_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $t->string('role', 15);
            $t->longText('content')->nullable();
            $t->json('tool_calls')->nullable();
            $t->unsignedInteger('tokens_in')->default(0);
            $t->unsignedInteger('tokens_out')->default(0);
            $t->string('status', 20)->default('ok');
            $t->timestamps();
            $t->index(['tenant_id', 'ai_conversation_id']);
        });

        Schema::create('ai_generations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('type', 30);
            $t->string('status', 15)->default('queued');
            $t->json('inputs')->nullable();
            $t->json('output')->nullable();
            $t->json('media')->nullable();
            $t->foreignId('ai_model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $t->nullableMorphs('target');
            $t->unsignedBigInteger('reviewed_by')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('reject_reason', 500)->nullable();
            $t->text('error')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'type', 'status']);
        });

        Schema::create('ai_trend_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 10);                    // own | market
            $t->string('country', 2)->nullable();
            $t->string('period', 20)->nullable();
            $t->json('items')->nullable();
            $t->timestamp('generated_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'kind', 'generated_at']);
        });
    }

    public function down(): void
    {
        foreach (['ai_trend_snapshots', 'ai_generations', 'ai_messages', 'ai_conversations', 'ai_usage_logs', 'ai_tenant_settings', 'ai_models', 'ai_providers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
