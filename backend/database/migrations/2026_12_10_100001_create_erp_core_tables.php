<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('code', 32);
            $t->json('name');
            $t->text('description')->nullable();
            $t->string('type', 16); // asset|liability|equity|revenue|expense
            $t->string('subtype', 40)->nullable();
            $t->foreignId('parent_id')->nullable()->constrained('erp_accounts')->nullOnDelete();
            $t->string('normal_balance', 6); // debit|credit
            $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->string('currency', 3)->nullable();
            $t->decimal('opening_balance', 18, 4)->default(0);
            $t->decimal('current_balance', 18, 4)->default(0); // cache, only JournalService writes it
            $t->string('role_key', 40)->nullable(); // system role resolved by AccountResolver (no hardcoded ids)
            $t->boolean('is_group')->default(false); // header accounts cannot be posted to
            $t->boolean('is_system')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'code']);
            $t->index(['tenant_id', 'role_key']);
            $t->index(['tenant_id', 'type']);
        });

        Schema::create('erp_fiscal_years', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 60);
            $t->date('starts_on');
            $t->date('ends_on');
            $t->string('status', 10)->default('open'); // open|closed
            $t->timestamps();
            $t->unique(['tenant_id', 'starts_on']);
        });

        Schema::create('erp_periods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('fiscal_year_id')->constrained('erp_fiscal_years')->cascadeOnDelete();
            $t->string('name', 60);
            $t->date('starts_on');
            $t->date('ends_on');
            $t->string('status', 10)->default('open'); // open|closed
            $t->timestamp('closed_at')->nullable();
            $t->foreignId('closed_by')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'starts_on']);
            $t->index(['tenant_id', 'ends_on']);
        });

        Schema::create('erp_journal_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('number', 32);
            $t->string('status', 10)->default('draft'); // draft|posted|reversed
            $t->string('type', 12)->default('manual'); // manual|auto|opening|adjusting|closing|reversal
            $t->date('entry_date');
            $t->foreignId('period_id')->nullable()->constrained('erp_periods')->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->string('currency', 3);
            $t->decimal('exchange_rate', 18, 8)->default(1);
            $t->string('source_type', 40)->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('idempotency_key', 120)->nullable();
            $t->foreignId('reverses_id')->nullable()->constrained('erp_journal_entries')->nullOnDelete();
            $t->foreignId('reversed_by_id')->nullable();
            $t->string('description', 500)->nullable();
            $t->string('attachment')->nullable();
            $t->decimal('total', 18, 4)->default(0);
            $t->foreignId('created_by')->nullable();
            $t->foreignId('posted_by')->nullable();
            $t->timestamp('posted_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'number']);
            $t->unique(['tenant_id', 'idempotency_key']);
            $t->index(['tenant_id', 'status', 'entry_date']);
            $t->index(['tenant_id', 'source_type', 'source_id']);
        });

        Schema::create('erp_journal_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('entry_id')->constrained('erp_journal_entries')->cascadeOnDelete();
            $t->foreignId('account_id')->constrained('erp_accounts');
            $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->decimal('debit', 18, 4)->default(0);
            $t->decimal('credit', 18, 4)->default(0);
            $t->decimal('original_amount', 18, 4)->nullable();
            $t->string('original_currency', 3)->nullable();
            $t->string('party_type', 20)->nullable(); // customer|supplier|owner|employee
            $t->unsignedBigInteger('party_id')->nullable();
            $t->string('memo', 300)->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'account_id']);
            $t->index(['tenant_id', 'party_type', 'party_id']);
            $t->index(['tenant_id', 'branch_id']);
        });

        Schema::create('erp_audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('action', 40); // journal.posted|journal.reversed|period.closed|period.reopened|account.archived ...
            $t->string('subject_type', 40)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->foreignId('user_id')->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tenant_id', 'action']);
            $t->index(['tenant_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['erp_audit_logs', 'erp_journal_lines', 'erp_journal_entries', 'erp_periods', 'erp_fiscal_years', 'erp_accounts'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
