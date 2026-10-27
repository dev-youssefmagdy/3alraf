<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_product_prices', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->unsignedBigInteger('supplier_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('variant_id')->default(0);
            $t->decimal('unit_cost', 12, 2);
            $t->unsignedInteger('moq')->default(1);
            $t->unsignedInteger('lead_time_days')->nullable();
            $t->date('valid_from')->nullable();
            $t->date('valid_to')->nullable();
            $t->boolean('is_preferred')->default(false);
            $t->string('note', 255)->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'product_id']);
            $t->index(['tenant_id', 'supplier_id']);
        });
        Schema::table('interactions', function (Blueprint $t) {
            $t->string('reviewed_category', 40)->nullable();
            $t->string('reviewed_intent', 40)->nullable();
            $t->string('reviewed_sentiment', 20)->nullable();
            $t->unsignedBigInteger('reviewed_by')->nullable();
            $t->timestamp('reviewed_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_product_prices');
        Schema::table('interactions', fn (Blueprint $t) => $t->dropColumn(['reviewed_category', 'reviewed_intent', 'reviewed_sentiment', 'reviewed_by', 'reviewed_at']));
    }
};
