<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Image search index (IMAGE_SEARCH_PLAN.md §3.2): one row per source (an `images` row of a product / variant, or a bundle's `image` column) and model.
 * vector = little-endian float32 × dim, L2-normalised (BLOB). status: pending (queued) → ready | failed. A row is "missing" work when there is none
 * for the current model, or its source_path / source_hash no longer match the file. No foreign keys: sources are polymorphic; observers + fill-missing prune.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_embeddings', function (Blueprint $t) {
            $t->id();
            $t->string('source_type', 16);                  // App\Enums\ImageEmbeddingSource
            $t->unsignedBigInteger('source_id');
            $t->unsignedBigInteger('product_id')->nullable();
            $t->unsignedBigInteger('product_variant_id')->nullable();
            $t->unsignedBigInteger('bundle_id')->nullable();
            $t->string('source_path', 500)->nullable();     // path the vector was computed from (cheap "replaced" check)
            $t->char('source_hash', 64)->nullable();        // sha256 of the file bytes
            $t->string('model', 64);
            $t->unsignedSmallInteger('dim')->nullable();
            $t->binary('vector')->nullable();
            $t->string('status', 16)->default('pending');   // App\Enums\ImageEmbeddingStatus
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->string('error', 500)->nullable();
            $t->timestamp('queued_at')->nullable();
            $t->timestamp('embedded_at')->nullable();
            $t->timestamp('failed_at')->nullable();
            $t->timestamps();

            $t->unique(['source_type', 'source_id', 'model']);
            $t->index(['model', 'status']);
            $t->index('product_id');
            $t->index('bundle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_embeddings');
    }
};
