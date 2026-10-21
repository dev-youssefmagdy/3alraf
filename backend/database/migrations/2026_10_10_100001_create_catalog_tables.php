<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $t) {
            $t->id();
            $t->json('name');
            $t->string('slug')->unique();
            $t->string('logo')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('attributes', function (Blueprint $t) {
            $t->id();
            $t->json('name');
            $t->string('code')->unique();
            $t->string('type', 20)->default('select'); // select|color|text
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $t->json('value');
            $t->string('color_hex', 9)->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $t->string('path')->default('');            // "1/5/9" ancestor ids incl. self
            $t->unsignedSmallInteger('depth')->default(0);
            $t->json('name');
            $t->json('description')->nullable();
            $t->string('slug')->unique();
            $t->string('image')->nullable();
            $t->string('icon')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->index('path');
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 20)->default('simple'); // simple|variable
            $t->json('name');
            $t->json('description')->nullable();
            $t->json('meta_title')->nullable();
            $t->json('meta_description')->nullable();
            $t->string('slug')->unique();
            $t->string('sku')->unique();
            $t->string('barcode', 13)->nullable()->unique();
            $t->decimal('weight', 10, 3)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('category_product', function (Blueprint $t) {
            $t->foreignId('category_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->primary(['category_id', 'product_id']);
        });

        Schema::create('product_variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('combination_hash', 64);          // sorted value ids, unique per product
            $t->string('sku')->unique();
            $t->string('barcode', 13)->unique();
            $t->decimal('weight', 10, 3)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['product_id', 'combination_hash']);
        });

        Schema::create('product_variant_values', function (Blueprint $t) {
            $t->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $t->primary(['product_variant_id', 'attribute_value_id']);
        });

        Schema::create('images', function (Blueprint $t) {
            $t->id();
            $t->morphs('imageable');
            $t->string('path');
            $t->string('thumb_path')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('tenant_products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->decimal('price', 14, 2)->default(0);
            $t->decimal('sale_price', 14, 2)->nullable();
            $t->decimal('cost', 14, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'product_id']);
        });

        Schema::create('tenant_product_variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tenant_product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $t->decimal('price', 14, 2)->nullable();      // null = inherit tenant_products.price
            $t->decimal('sale_price', 14, 2)->nullable();
            $t->decimal('cost', 14, 2)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_product_id', 'product_variant_id'], 'tpv_unique');
        });
    }

    public function down(): void
    {
        foreach (['tenant_product_variants', 'tenant_products', 'images', 'product_variant_values', 'product_variants', 'category_product', 'products', 'categories', 'attribute_values', 'attributes', 'brands'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
