<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', fn (Blueprint $t) => $t->json('attribution')->nullable());
        Schema::table('seasonal_events', function (Blueprint $t) {
            $t->boolean('date_confirmed')->default(false);
            $t->timestamp('confirmed_at')->nullable();
            $t->unsignedBigInteger('confirmed_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('carts', fn (Blueprint $t) => $t->dropColumn('attribution'));
        Schema::table('seasonal_events', fn (Blueprint $t) => $t->dropColumn(['date_confirmed', 'confirmed_at', 'confirmed_by']));
    }
};
