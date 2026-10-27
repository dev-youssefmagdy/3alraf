<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_memory_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 24); // voice|preferred_words|prohibited_words|reply_policy|faq|approved_claim|offer_rule
            $t->string('title', 160);
            $t->text('body');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('version')->default(1);
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'kind', 'is_active']);
        });
        Schema::create('brand_memory_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('entry_id')->constrained('brand_memory_entries')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->string('title', 160);
            $t->text('body');
            $t->boolean('is_active');
            $t->unsignedBigInteger('changed_by')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->unique(['entry_id', 'version']);
        });
        Schema::create('approved_product_claims', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('product_id');
            $t->string('type', 16); // ingredient|benefit|authenticity|result|other
            $t->string('claim', 300);
            $t->boolean('is_active')->default(true);
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'product_id', 'is_active']);
        });
        Schema::create('content_assets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->unsignedBigInteger('ai_generation_id')->nullable();
            $t->string('type', 24);
            $t->string('variant', 40)->nullable();
            $t->string('locale', 8)->nullable();
            $t->string('title', 200)->nullable();
            $t->text('body');
            $t->string('status', 12)->default('approved'); // approved|published|retired
            $t->unsignedInteger('version')->default(1);
            $t->string('utm_content', 60)->nullable();
            $t->string('tracked_url', 600)->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'product_id']);
            $t->index(['tenant_id', 'ai_generation_id']);
            $t->unique(['tenant_id', 'utm_content']);
        });
        Schema::create('content_calendar', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('content_asset_id')->constrained('content_assets')->cascadeOnDelete();
            $t->string('channel', 20);
            $t->dateTime('scheduled_for');
            $t->string('status', 10)->default('planned'); // planned|published|skipped
            $t->string('note', 300)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        foreach (['content_calendar', 'content_assets', 'approved_product_claims', 'brand_memory_versions', 'brand_memory_entries'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
