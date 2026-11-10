<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Commission / sales-bonus split of a payout (docs/CALCULATIONS.md §Affiliate sales bonus), stored at request time and recomputed when paid,
 * so a rejected payout (whose rows are released) keeps its split. Existing rows: bonus = Σ linked bonuses, commission = amount − bonus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_payouts', function (Blueprint $t) {
            $t->decimal('commission_amount', 14, 2)->default(0)->after('amount');
            $t->decimal('bonus_amount', 14, 2)->default(0)->after('commission_amount');
        });
        DB::statement("UPDATE affiliate_payouts p SET p.bonus_amount = COALESCE((SELECT SUM(b.amount) FROM affiliate_bonuses b WHERE b.payout_id = p.id AND b.status <> 'voided'), 0)");
        DB::statement('UPDATE affiliate_payouts SET commission_amount = GREATEST(amount - bonus_amount, 0)');
    }

    public function down(): void
    {
        Schema::table('affiliate_payouts', fn (Blueprint $t) => $t->dropColumn(['commission_amount', 'bonus_amount']));
    }
};
