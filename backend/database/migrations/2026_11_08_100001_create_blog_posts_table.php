<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('slug', 100);
            $t->json('title');
            $t->json('excerpt')->nullable();
            $t->json('content')->nullable();
            $t->json('meta_description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
