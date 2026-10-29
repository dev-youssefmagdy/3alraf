<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which of the suggested products the shopper actually added to the cart (cart_at only says that something was).
        Schema::table('gift_finder_sessions', function (Blueprint $t) {
            $t->json('cart_ids')->nullable()->after('results_count');
        });
    }

    public function down(): void
    {
        Schema::table('gift_finder_sessions', fn (Blueprint $t) => $t->dropColumn('cart_ids'));
    }
};
