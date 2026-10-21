<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('owner_type', 60);
            $t->unsignedBigInteger('owner_id');
            $t->foreignId('affiliate_id')->nullable()->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('affiliate_key')->default(0); // = affiliate_id or 0 (maintained by the model): makes the unique key null-safe
            $t->text('url');
            $t->char('url_hash', 40);
            $t->string('path');
            $t->timestamps();
            $t->unique(['owner_type', 'owner_id', 'affiliate_key']);
            $t->index(['tenant_id', 'owner_type', 'owner_id']);
            $t->index(['tenant_id', 'affiliate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
