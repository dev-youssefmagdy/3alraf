<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mobile push tokens (FCM). Scoped to a tenant (= store/country) and optionally a signed-in customer. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('token', 512);
            $t->string('token_hash', 64);
            $t->string('platform', 10);
            $t->string('locale', 5)->nullable();
            $t->string('app_version', 20)->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'token_hash']);
            $t->index(['tenant_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
