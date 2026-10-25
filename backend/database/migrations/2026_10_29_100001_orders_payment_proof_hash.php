<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->char('payment_proof_hash', 64)->nullable()->after('payment_proof'); // sha256 of the proof file (reuse detection)
            $t->index(['tenant_id', 'payment_proof_hash'], 'orders_tenant_proof_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex('orders_tenant_proof_hash_idx');
            $t->dropColumn('payment_proof_hash');
        });
    }
};
