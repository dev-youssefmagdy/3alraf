<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P8 (MARKETER_PLAN.md, Decision 6): public product videos for the storefront gallery. Separate from the private social-media library (social_media_assets).
 * sort_order shares one numbering with the product's rows in `images`, so the gallery can mix both (App\Services\Storefront\ProductGallery).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_videos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('poster_path')->nullable();
            $t->string('poster_thumb_path')->nullable();
            $t->string('mime', 32);
            $t->unsignedBigInteger('size_bytes');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_videos');
    }
};
