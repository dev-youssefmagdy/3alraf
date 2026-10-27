<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_returns', function (Blueprint $t) {
            $t->string('reason_code', 24)->nullable(); // structured reason (growth return intelligence); free-text `reason` stays
            $t->index(['tenant_id', 'reason_code']);
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->string('cancel_reason_code', 24)->nullable();
        });
        Schema::create('product_supply_terms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('supplier_id')->nullable();
            $t->unsignedSmallInteger('lead_time_days')->default(14);
            $t->unsignedInteger('moq')->default(1);
            $t->unsignedSmallInteger('shelf_life_days')->nullable(); // set = perishable / expiring stock
            $t->timestamps();
            $t->unique(['tenant_id', 'product_id']);
        });
        Schema::create('trend_candidates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('week', 8);
            $t->string('item_key', 40);
            $t->string('name', 200);
            $t->string('source', 20); // internal|market
            $t->unsignedBigInteger('product_id')->nullable();
            $t->decimal('score', 5, 1)->default(0);
            $t->json('components');
            $t->json('evidence');
            $t->json('uncertainty');
            $t->string('confidence', 10)->default('low');
            $t->string('status', 12)->default('new'); // new|testing|dismissed
            $t->unsignedBigInteger('experiment_id')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'week', 'item_key']);
            $t->index(['tenant_id', 'status', 'score']);
        });
        Schema::create('ceo_challenges', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('week', 8);
            $t->string('rule', 30);
            $t->string('question', 300);
            $t->text('hypothesis');
            $t->text('experiment');
            $t->string('metric', 200);
            $t->unsignedSmallInteger('duration_days');
            $t->date('review_on');
            $t->json('evidence');
            $t->string('status', 10)->default('open'); // open|reviewed|skipped
            $t->text('result_note')->nullable();
            $t->unsignedBigInteger('experiment_id')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'week']);
            $t->index(['tenant_id', 'rule']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ceo_challenges');
        Schema::dropIfExists('trend_candidates');
        Schema::dropIfExists('product_supply_terms');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('cancel_reason_code'));
        Schema::table('order_returns', function (Blueprint $t) {
            $t->dropIndex(['tenant_id', 'reason_code']);
            $t->dropColumn('reason_code');
        });
    }
};
