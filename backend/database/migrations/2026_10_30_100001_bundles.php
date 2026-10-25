<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->json('name');
            $t->json('description')->nullable();
            $t->string('slug', 220);
            $t->string('image')->nullable();
            $t->decimal('price', 14, 2);
            $t->boolean('is_active')->default(true);
            $t->dateTime('starts_at')->nullable();
            $t->dateTime('ends_at')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['tenant_id', 'slug']);
            $t->index(['tenant_id', 'is_active', 'sort_order']);
        });

        Schema::create('bundle_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('bundle_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('product_variant_id')->default(0);   // 0 = simple product
            $t->unsignedInteger('quantity')->default(1);                // units per ONE bundle
            $t->timestamps();
            $t->unique(['bundle_id', 'product_id', 'product_variant_id'], 'bundle_items_unique');
        });

        // A bundle cart line: bundle_id > 0, product_id NULL, variant 0. Product lines keep bundle_id = 0.
        Schema::table('cart_items', function (Blueprint $t) {
            $t->unsignedBigInteger('bundle_id')->default(0)->after('cart_id');
        });
        Schema::table('cart_items', function (Blueprint $t) {
            $t->unsignedBigInteger('product_id')->nullable()->change();
            $t->unique(['cart_id', 'bundle_id', 'product_id', 'product_variant_id'], 'cart_items_unique2');
        });
        Schema::table('cart_items', fn (Blueprint $t) => $t->dropUnique('cart_items_unique'));

        Schema::table('order_items', function (Blueprint $t) {
            $t->unsignedBigInteger('bundle_id')->nullable()->after('flash_sale_item_id');
            $t->string('bundle_name')->nullable()->after('bundle_id');
            $t->string('bundle_group', 36)->nullable()->after('bundle_name');
            $t->index('bundle_id', 'oi_bundle');
            $t->index(['order_id', 'bundle_group'], 'oi_order_bundle_group');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $t) {
            $t->dropIndex('oi_bundle');
            $t->dropIndex('oi_order_bundle_group');
            $t->dropColumn(['bundle_id', 'bundle_name', 'bundle_group']);
        });
        \Illuminate\Support\Facades\DB::table('cart_items')->where('bundle_id', '>', 0)->delete();
        Schema::table('cart_items', function (Blueprint $t) {
            $t->unique(['cart_id', 'product_id', 'product_variant_id'], 'cart_items_unique');
        });
        Schema::table('cart_items', fn (Blueprint $t) => $t->dropUnique('cart_items_unique2'));
        Schema::table('cart_items', function (Blueprint $t) {
            $t->unsignedBigInteger('product_id')->nullable(false)->change();
            $t->dropColumn('bundle_id');
        });
        Schema::dropIfExists('bundle_items');
        Schema::dropIfExists('bundles');
    }
};
