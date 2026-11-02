<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_owners', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 120);
            $t->string('phone', 32)->nullable();
            $t->decimal('ownership_percent', 7, 4)->default(0);
            $t->boolean('is_eligible')->default(true); // eligible for profit distribution
            $t->foreignId('tenant_admin_id')->nullable()->constrained('tenant_admins')->nullOnDelete();
            $t->timestamps();
            $t->index(['tenant_id', 'name']);
        });

        Schema::create('erp_owner_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('owner_id')->constrained('erp_owners')->cascadeOnDelete();
            $t->string('type', 14); // contribution|withdrawal|distribution
            $t->decimal('amount', 18, 4);
            $t->date('txn_date');
            $t->foreignId('journal_entry_id')->nullable()->constrained('erp_journal_entries')->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->string('account_role', 24)->default('cash'); // cash-like account used
            $t->string('note', 300)->nullable();
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'owner_id', 'txn_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_owner_transactions');
        Schema::dropIfExists('erp_owners');
    }
};
