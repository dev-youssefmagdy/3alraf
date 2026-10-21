<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->decimal('cod_fee', 14, 2)->default(0)->after('payment_fee_charged');   // COD extra fee charged to the customer (inside total & cod_amount)
            $t->json('cod_fee_label')->nullable()->after('cod_fee');                    // label snapshot (locale => text)
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropColumn(['cod_fee', 'cod_fee_label']);
        });
    }
};
