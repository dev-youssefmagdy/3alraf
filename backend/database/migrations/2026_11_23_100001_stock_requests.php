<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('product_id')->index();
            $t->unsignedBigInteger('product_variant_id')->nullable(); // null = the product / any variant
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->string('name', 120);
            $t->string('phone', 30);
            $t->string('phone_country_code', 6)->nullable();
            $t->text('note')->nullable();
            $t->string('status', 12)->default('new'); // new|contacted|closed
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
        });

        Schema::table('tenant_products', fn (Blueprint $t) => $t->unsignedInteger('min_stock_alert')->nullable()->after('cost'));
    }

    public function down(): void
    {
        Schema::table('tenant_products', fn (Blueprint $t) => $t->dropColumn('min_stock_alert'));
        Schema::dropIfExists('stock_requests');
    }
};
