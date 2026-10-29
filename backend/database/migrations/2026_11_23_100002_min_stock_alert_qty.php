<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_products', fn (Blueprint $t) => $t->renameColumn('min_stock_alert', 'min_stock_alert_qty'));
        // per-variant override; null = use the product's value
        Schema::table('tenant_product_variants', fn (Blueprint $t) => $t->unsignedInteger('min_stock_alert_qty')->nullable()->after('cost'));
    }

    public function down(): void
    {
        Schema::table('tenant_product_variants', fn (Blueprint $t) => $t->dropColumn('min_stock_alert_qty'));
        Schema::table('tenant_products', fn (Blueprint $t) => $t->renameColumn('min_stock_alert_qty', 'min_stock_alert'));
    }
};
