<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasonal_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('key', 40);
            $t->unsignedSmallInteger('year');
            $t->string('name_ar', 120);
            $t->string('name_en', 120);
            $t->date('starts_on');
            $t->date('ends_on');
            $t->boolean('hijri')->default(false);
            $t->json('category_ids')->nullable();
            $t->unsignedSmallInteger('lead_time_days')->default(21);
            $t->text('notes')->nullable();
            $t->string('status', 12)->default('planned'); // planned|closed
            $t->timestamps();
            $t->unique(['tenant_id', 'key', 'year']);
            $t->index(['tenant_id', 'starts_on']);
        });
        Schema::create('seasonal_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('seasonal_event_id')->constrained('seasonal_events')->cascadeOnDelete();
            $t->string('code', 30);
            $t->string('title', 200);
            $t->date('due_on')->nullable();
            $t->timestamp('done_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'seasonal_event_id']);
        });
        Schema::create('seasonal_memories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('seasonal_event_id')->unique()->constrained('seasonal_events')->cascadeOnDelete();
            $t->json('performance')->nullable(); // computed: best products, profitable bundles, orders, revenue
            $t->text('content_formats')->nullable();
            $t->text('start_timing')->nullable();
            $t->text('objections')->nullable();
            $t->text('lessons')->nullable();
            $t->timestamps();
        });
        Schema::create('bundle_ab_tests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('bundle_id')->constrained('bundles')->cascadeOnDelete();
            $t->unsignedBigInteger('experiment_id')->nullable();
            $t->string('status', 12)->default('running'); // running|stopped
            $t->unsignedTinyInteger('split_b')->default(50);
            $t->unsignedInteger('min_sample')->default(100);
            $t->string('winner', 1)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'bundle_id', 'status']);
        });
        Schema::create('bundle_ab_variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('bundle_ab_test_id')->constrained('bundle_ab_tests')->cascadeOnDelete();
            $t->string('label', 1); // A = control (the bundle as is), B = challenger
            $t->json('name')->nullable();
            $t->json('description')->nullable();
            $t->string('image')->nullable();
            $t->unsignedInteger('exposures')->default(0);
            $t->unsignedInteger('carts')->default(0);
            $t->timestamps();
            $t->unique(['bundle_ab_test_id', 'label']);
        });
        Schema::create('gift_finder_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('token', 40)->unique();
            $t->string('occasion', 40)->nullable();
            $t->string('recipient', 40)->nullable();
            $t->decimal('budget', 12, 2);
            $t->unsignedBigInteger('category_id')->nullable();
            $t->string('restrictions', 200)->nullable();
            $t->json('result_ids')->nullable();
            $t->unsignedSmallInteger('results_count')->default(0);
            $t->timestamp('cart_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['gift_finder_sessions', 'bundle_ab_variants', 'bundle_ab_tests', 'seasonal_memories', 'seasonal_tasks', 'seasonal_events'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
