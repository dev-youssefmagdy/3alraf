<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tenant-owned storefront text overlay over the central product (AI product editor): {field: {locale: text}}. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_products', function (Blueprint $t) {
            $t->json('content')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_products', function (Blueprint $t) {
            $t->dropColumn('content');
        });
    }
};
