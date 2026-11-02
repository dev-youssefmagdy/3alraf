<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 4: optional ledger mapping for expense categories + paid/unpaid state on expenses (old rows keep working: NULL account => role other_expense, paid). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $t) {
            $t->foreignId('erp_account_id')->nullable()->after('is_active')->constrained('erp_accounts')->nullOnDelete();
        });
        Schema::table('expenses', function (Blueprint $t) {
            $t->boolean('is_paid')->default(true)->after('amount');
            $t->string('paid_from', 30)->nullable()->after('is_paid'); // account role key of the paying asset (cash, bank, wallet...); NULL => cash
        });
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $t) => $t->dropColumn(['is_paid', 'paid_from']));
        Schema::table('expense_categories', function (Blueprint $t) {
            $t->dropConstrainedForeignId('erp_account_id');
        });
    }
};
