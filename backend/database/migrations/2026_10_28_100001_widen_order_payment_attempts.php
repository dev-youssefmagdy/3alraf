<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** orders.payment_attempts: tinyint (max 255) → smallint; OrderPaymentService::MAX_PAYMENT_ATTEMPTS caps it far below either. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->unsignedSmallInteger('payment_attempts')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->unsignedTinyInteger('payment_attempts')->default(0)->change();
        });
    }
};
