<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ERP accounting audit fixes (docs/qa/ERP_AUDIT_FINDINGS.md, pass 1):
 *  - order_returns.refund_from / refund_online : account the refund was paid FROM and the part actually sent back through the provider (ERP-001/010)
 *  - affiliate_payouts.paid_from              : account an affiliate payout was paid from (ERP-003)
 *  - orders.counter_sale                       : POS sale paid at the counter, collected into pos_cash (ERP-014)
 *  - expenses.supplier_id                      : payee of an unpaid expense (party on the payable line, ERP-013)
 *  - erp_settings.revenue_recognition          : tenants that already posted order journals keep the legacy "confirmed" policy
 *    (new tenants default to "shipped"), so an existing ledger is never re-timed silently (ERP-011)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_returns', function (Blueprint $t) {
            $t->string('refund_from', 30)->nullable()->after('refund_amount');
            $t->decimal('refund_online', 14, 2)->default(0)->after('refund_from');
        });
        Schema::table('affiliate_payouts', function (Blueprint $t) {
            $t->string('paid_from', 30)->nullable()->after('amount');
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->boolean('counter_sale')->default(false)->after('stock_deducted');
        });
        Schema::table('expenses', function (Blueprint $t) {
            $t->foreignId('supplier_id')->nullable()->after('paid_from')->constrained('suppliers')->nullOnDelete();
        });

        $now = now();
        $tenants = DB::table('erp_journal_entries')->where('source_type', 'order')->where('idempotency_key', 'like', 'order:%:confirm%')->distinct()->pluck('tenant_id');
        foreach ($tenants as $tid) {
            DB::table('erp_settings')->insertOrIgnore(['tenant_id' => $tid, 'key' => 'revenue_recognition', 'value' => json_encode('confirmed'), 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $t) => $t->dropConstrainedForeignId('supplier_id'));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('counter_sale'));
        Schema::table('affiliate_payouts', fn (Blueprint $t) => $t->dropColumn('paid_from'));
        Schema::table('order_returns', fn (Blueprint $t) => $t->dropColumn(['refund_from', 'refund_online']));
        DB::table('erp_settings')->where('key', 'revenue_recognition')->delete();
    }
};
