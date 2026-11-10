<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Affiliate sales bonus (AFFILIATE_BONUS_PLAN.md, docs/CALCULATIONS.md §Affiliate sales bonus): one row per milestone reached.
 * threshold/amount are copied from the settings at award time, so changing the settings never rewrites earned bonuses.
 * unique(affiliate_id, milestone) is the database guard against double awards. Launch date = migration time (no back-pay).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_bonuses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $t->foreignId('payout_id')->nullable()->constrained('affiliate_payouts')->nullOnDelete();
            $t->unsignedInteger('milestone'); // 1..n per affiliate
            $t->decimal('threshold', 14, 2);
            $t->decimal('amount', 14, 2);
            $t->decimal('sales_at_award', 14, 2);
            $t->string('status', 12)->default('approved'); // approved|paid|voided (CommissionStatus)
            $t->timestamps();
            $t->unique(['affiliate_id', 'milestone']);
            $t->index(['affiliate_id', 'status', 'payout_id']);
        });

        // Launch time in the store timezone, the same clock orders.created_at is written in (setting `timezone`, else the app default).
        $tz = Setting::get('timezone');
        $tz = $tz && in_array($tz, timezone_identifiers_list(), true) ? $tz : config('app.timezone');
        $defaults = [
            'affiliate_bonus_enabled' => true,
            'affiliate_bonus_threshold' => '10000.00',
            'affiliate_bonus_amount' => '200.00',
            'affiliate_bonus_started_at' => now($tz)->format('Y-m-d H:i:s'),
        ];
        foreach ($defaults as $key => $value) {
            if (! Setting::query()->where('key', $key)->exists()) {
                Setting::put($key, $value);
            }
        }
        Setting::flush();
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_bonuses');
        Setting::query()->whereIn('key', ['affiliate_bonus_enabled', 'affiliate_bonus_threshold', 'affiliate_bonus_amount', 'affiliate_bonus_started_at'])->delete();
        Setting::flush();
    }
};
