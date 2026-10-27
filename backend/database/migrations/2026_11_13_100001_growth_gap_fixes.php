<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Privacy-friendly traffic counters: no IP, no user agent, no session id stored (unique sessions are deduplicated via a short-lived cache key).
        Schema::create('storefront_visits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->date('visit_date');
            $t->unsignedBigInteger('product_id')->default(0); // 0 = site-wide row
            $t->string('channel', 20)->default('direct');
            $t->unsignedInteger('visits')->default(0);
            $t->unsignedInteger('unique_sessions')->default(0);
            $t->timestamps();
            $t->unique(['tenant_id', 'visit_date', 'product_id', 'channel'], 'storefront_visits_unique');
        });

        Schema::table('orders', function (Blueprint $t) {
            $t->string('channel', 20)->nullable()->after('affiliate_id');
            $t->json('attribution')->nullable()->after('channel');
            $t->index(['tenant_id', 'channel'], 'orders_tenant_channel_idx');
        });

        Schema::create('growth_brief_decisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('recommendation_key', 120);
            $t->string('kind', 60)->nullable();
            $t->string('status', 12); // accepted | dismissed | done
            $t->string('owner', 100)->nullable();
            $t->string('note', 500)->nullable();
            $t->unsignedBigInteger('decided_by')->nullable();
            $t->unsignedBigInteger('experiment_id')->nullable();
            $t->string('outcome_metric', 60)->nullable();
            $t->decimal('metric_before', 16, 2)->nullable();
            $t->decimal('metric_after', 16, 2)->nullable();
            $t->json('evidence')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'recommendation_key'], 'brief_decisions_unique');
            $t->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_brief_decisions');
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex('orders_tenant_channel_idx');
            $t->dropColumn(['channel', 'attribution']);
        });
        Schema::dropIfExists('storefront_visits');
    }
};
