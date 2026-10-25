<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packaging_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->json('name');
            $t->json('description')->nullable();
            $t->string('image')->nullable();
            $t->decimal('price', 14, 2)->default(0);   // customer-facing fee
            $t->decimal('cost', 14, 2)->default(0);    // merchant cost (never exposed)
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['tenant_id', 'is_active', 'sort_order']);
        });

        Schema::table('carts', function (Blueprint $t) {
            $t->unsignedBigInteger('packaging_option_id')->nullable();
        });

        Schema::table('orders', function (Blueprint $t) {
            $t->unsignedBigInteger('packaging_option_id')->nullable()->index();
            $t->json('packaging_name')->nullable();
            $t->decimal('packaging_fee', 14, 2)->default(0);
            $t->decimal('packaging_cost', 14, 2)->default(0);
            $t->decimal('packaging_tax', 14, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex(['packaging_option_id']);
            $t->dropColumn(['packaging_option_id', 'packaging_name', 'packaging_fee', 'packaging_cost', 'packaging_tax']);
        });
        Schema::table('carts', fn (Blueprint $t) => $t->dropColumn('packaging_option_id'));
        Schema::dropIfExists('packaging_options');
    }
};
