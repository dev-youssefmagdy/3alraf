<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_fixed_assets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 150);
            $t->string('category', 80)->nullable();
            $t->date('acquisition_date');
            $t->decimal('cost', 18, 4);
            $t->decimal('salvage_value', 18, 4)->default(0);
            $t->unsignedSmallInteger('useful_life_months');
            $t->string('method', 20)->default('straight_line'); // straight_line|declining_balance
            $t->string('frequency', 10)->default('monthly');
            $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $t->string('status', 10)->default('active'); // active|disposed
            $t->decimal('accumulated_depreciation', 18, 4)->default(0); // cache, only AssetService writes it
            $t->string('last_depreciated_period', 7)->nullable(); // YYYY-MM
            $t->date('disposed_at')->nullable();
            $t->decimal('disposal_proceeds', 18, 4)->nullable();
            $t->decimal('disposal_gain_loss', 18, 4)->nullable();
            $t->foreignId('registration_entry_id')->nullable()->constrained('erp_journal_entries')->nullOnDelete();
            $t->foreignId('disposal_entry_id')->nullable()->constrained('erp_journal_entries')->nullOnDelete();
            $t->string('note', 300)->nullable();
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_fixed_assets');
    }
};
