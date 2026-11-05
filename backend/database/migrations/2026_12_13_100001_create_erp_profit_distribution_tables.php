<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('key', 80);
            $t->json('value')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'key']);
        });

        Schema::create('erp_profit_distributions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('fiscal_year_id')->nullable()->constrained('erp_fiscal_years')->nullOnDelete();
            $t->date('period_start');
            $t->date('period_end');
            $t->string('status', 12)->default('draft'); // draft|approved|posted|cancelled
            $t->decimal('ledger_profit', 18, 4)->default(0);   // net profit as booked in the ledger (before estimated tax)
            $t->decimal('tax_amount', 18, 4)->default(0);
            $t->decimal('net_profit', 18, 4)->default(0);       // after tax
            $t->decimal('retained_before', 18, 4)->default(0);  // retained earnings brought forward (negative = prior losses)
            $t->decimal('reserve_amount', 18, 4)->default(0);
            $t->decimal('distributable_profit', 18, 4)->default(0);
            $t->decimal('cash_available', 18, 4)->default(0);   // distributable cash snapshot
            $t->decimal('distributable', 18, 4)->default(0);    // min(profit, cash)
            $t->decimal('distributed_amount', 18, 4)->default(0);
            $t->decimal('retained_after', 18, 4)->default(0);
            $t->boolean('acknowledged_open')->default(false);
            // set to "start|end" while the run is alive, NULL once cancelled: unique => one live run per exact period
            $t->string('active_key', 30)->nullable();
            $t->string('note', 300)->nullable();
            $t->json('snapshot')->nullable();
            $t->foreignId('created_by')->nullable();
            $t->foreignId('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('posted_by')->nullable();
            $t->timestamp('posted_at')->nullable();
            $t->foreignId('journal_entry_id')->nullable()->constrained('erp_journal_entries')->nullOnDelete();
            $t->foreignId('transfer_entry_id')->nullable()->constrained('erp_journal_entries')->nullOnDelete();
            $t->timestamps();
            $t->unique(['tenant_id', 'active_key']);
            $t->index(['tenant_id', 'period_start', 'period_end']);
        });

        Schema::create('erp_profit_distribution_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('distribution_id')->constrained('erp_profit_distributions')->cascadeOnDelete();
            $t->foreignId('owner_id')->constrained('erp_owners')->cascadeOnDelete();
            $t->decimal('percent', 9, 4)->default(0);
            $t->decimal('amount', 18, 4)->default(0);
            $t->boolean('paid')->default(false);
            $t->timestamp('paid_at')->nullable();
            $t->string('paid_account', 24)->nullable();
            $t->foreignId('paid_by')->nullable();
            $t->foreignId('payment_entry_id')->nullable()->constrained('erp_journal_entries')->nullOnDelete();
            $t->timestamps();
            $t->unique(['distribution_id', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_profit_distribution_lines');
        Schema::dropIfExists('erp_profit_distributions');
        Schema::dropIfExists('erp_settings');
    }
};
