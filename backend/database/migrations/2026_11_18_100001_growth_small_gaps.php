<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interactions', function (Blueprint $t) {
            $t->unsignedBigInteger('customer_id')->nullable(); // matched by hashed phone/email at ingest (erasure on customer deletion)
            $t->index(['tenant_id', 'customer_id']);
        });
        Schema::create('ambassador_rewards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('ambassador_id');
            $t->unsignedBigInteger('affiliate_id');
            $t->unsignedBigInteger('order_id');
            $t->decimal('amount', 12, 2)->default(0);
            $t->string('status', 12)->default('pending'); // pending | valid | excluded | paid
            $t->string('reason', 60)->nullable();
            $t->timestamp('valid_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'order_id']);
            $t->index(['tenant_id', 'ambassador_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambassador_rewards');
        Schema::table('interactions', function (Blueprint $t) {
            $t->dropIndex(['tenant_id', 'customer_id']);
            $t->dropColumn('customer_id');
        });
    }
};
