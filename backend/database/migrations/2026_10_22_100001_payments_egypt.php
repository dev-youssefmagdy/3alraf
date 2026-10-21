<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('provider', 30);
            $t->string('event_id', 120);                  // provider transaction/notification id (+ status where the provider re-sends)
            $t->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 20);                     // paid|failed|cancelled|pending|refunded
            $t->string('outcome', 20)->default('applied'); // applied|ignored
            $t->timestamps();
            $t->unique(['provider', 'event_id']);
            $t->index(['tenant_id', 'order_id']);
        });

        Schema::table('orders', function (Blueprint $t) {
            $t->decimal('cod_amount', 14, 2)->default(0)->after('total');          // cash the carrier collects (COD only)
            $t->decimal('prepaid_shipping', 14, 2)->default(0)->after('cod_amount'); // shipping paid upfront via online/wallet
            $t->unsignedTinyInteger('payment_attempts')->default(0)->after('gateway_reference'); // online payment links created
            $t->foreignId('prepay_method_id')->nullable()->after('payment_method_id')->constrained('payment_methods')->nullOnDelete();
            $t->index(['tenant_id', 'shipping_company_id', 'status'], 'orders_tenant_carrier_status_idx');
        });

        // Existing proofs move from the public disk to the private one.
        foreach (DB::table('orders')->whereNotNull('payment_proof')->get(['id', 'payment_proof']) as $o) {
            if (Storage::disk('public')->exists($o->payment_proof)) {
                Storage::disk('private')->put($o->payment_proof, Storage::disk('public')->get($o->payment_proof));
                Storage::disk('public')->delete($o->payment_proof);
            }
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex('orders_tenant_carrier_status_idx');
            $t->dropConstrainedForeignId('prepay_method_id');
            $t->dropColumn(['cod_amount', 'prepaid_shipping', 'payment_attempts']);
        });
        Schema::dropIfExists('payment_events');
    }
};
