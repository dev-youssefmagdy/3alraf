<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Casting for products that live only on 3alraf.com: a read-only SNAPSHOT of the live product (fetched through the store MCP) + nullable links. Additive. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casting_live_products', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('live_id')->unique();       // product id ON 3alraf.com
            $t->string('sku', 100)->nullable();
            $t->json('name');                                  // {ar, en}
            $t->json('description')->nullable();               // {ar, en} plain text
            $t->json('categories')->nullable();
            $t->json('tags')->nullable();
            $t->decimal('price', 12, 2)->nullable();
            $t->json('gift_recipients')->nullable();
            $t->string('url', 500)->nullable();
            $t->json('image_urls')->nullable();
            $t->string('image_disk', 30)->nullable();
            $t->string('image_path', 255)->nullable();         // private copy of the primary photo (PNG); the live original is never touched
            $t->timestamp('fetched_at')->nullable();
            $t->timestamps();
        });
        Schema::table('casting_sessions', function (Blueprint $t) {
            $t->foreignId('live_product_id')->nullable()->after('product_id')->constrained('casting_live_products')->nullOnDelete();
        });
        Schema::table('model_generation_history', function (Blueprint $t) {
            $t->foreignId('live_product_id')->nullable()->after('product_id')->constrained('casting_live_products')->nullOnDelete();
        });
        Schema::table('casting_sessions', function (Blueprint $t) {
            $t->dropForeign(['product_id']);
        });
        Schema::table('casting_sessions', function (Blueprint $t) {
            $t->unsignedBigInteger('product_id')->nullable()->change();
            $t->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('model_generation_history', fn (Blueprint $t) => $t->dropConstrainedForeignId('live_product_id'));
        Schema::table('casting_sessions', fn (Blueprint $t) => $t->dropConstrainedForeignId('live_product_id'));
        Schema::dropIfExists('casting_live_products');
    }
};
