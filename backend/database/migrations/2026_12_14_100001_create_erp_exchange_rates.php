<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* 1 unit of from_currency = rate units of to_currency, valid from effective_date. tenant_id NULL = global (set centrally); a tenant row overrides the global one for that tenant. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_exchange_rates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('from_currency', 3);
            $t->string('to_currency', 3);
            $t->decimal('rate', 18, 8);
            $t->date('effective_date');
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'from_currency', 'to_currency', 'effective_date'], 'erp_rates_unique');
            $t->index(['from_currency', 'to_currency', 'effective_date'], 'erp_rates_pair_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_exchange_rates');
    }
};
