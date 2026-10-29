<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gift Finder tags, per store: is it a sensible gift, for whom (recipient keys) and for which occasions (occasion keys). Empty list = no restriction.
        Schema::table('tenant_products', function (Blueprint $t) {
            $t->boolean('gift_suitable')->default(false)->after('content')->index();
            $t->json('gift_recipients')->nullable()->after('gift_suitable');
            $t->json('gift_occasions')->nullable()->after('gift_recipients');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_products', fn (Blueprint $t) => $t->dropColumn(['gift_suitable', 'gift_recipients', 'gift_occasions']));
    }
};
