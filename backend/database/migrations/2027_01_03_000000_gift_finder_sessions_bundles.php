<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3 (MARKETER_PLAN.md): the Gift Finder ranks products AND bundles in one list.
 * bundle_ids = suggested bundles (result_ids keeps the suggested products), cart_bundle_ids = bundles the shopper added to the cart,
 * params = the other answers needed to re-render a shared result link: {type, sort, relaxed[], order[]} (order = ranked "product:ID"/"bundle:ID" keys).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_finder_sessions', function (Blueprint $t) {
            $t->json('bundle_ids')->nullable()->after('result_ids');
            $t->json('cart_bundle_ids')->nullable()->after('cart_ids');
            $t->json('params')->nullable()->after('restrictions');
        });
    }

    public function down(): void
    {
        Schema::table('gift_finder_sessions', fn (Blueprint $t) => $t->dropColumn(['bundle_ids', 'cart_bundle_ids', 'params']));
    }
};
