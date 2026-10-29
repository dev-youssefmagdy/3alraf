<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ledger of the AVIF migration: one row per source file => resumable, idempotent, auditable, and the data needed to roll references back. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_optimizations', function (Blueprint $t) {
            $t->id();
            $t->string('disk', 40);
            $t->string('source_path', 500);
            $t->string('output_path', 500)->nullable();
            $t->string('category', 40);                 // db source: images, brands, categories, banners, …
            $t->string('profile', 40);
            $t->string('status', 20)->index();          // optimized | exceeds_limit | missing | invalid | skipped | failed
            $t->unsignedInteger('source_bytes')->nullable();
            $t->unsignedInteger('output_bytes')->nullable();
            $t->unsignedSmallInteger('width')->nullable();
            $t->unsignedSmallInteger('height')->nullable();
            $t->unsignedTinyInteger('quality')->nullable();
            $t->unsignedInteger('references_updated')->default(0);
            $t->timestamp('applied_at')->nullable();    // set while DB references point at output_path
            $t->string('reason', 500)->nullable();
            $t->timestamps();
            $t->unique(['disk', 'source_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_optimizations');
    }
};
