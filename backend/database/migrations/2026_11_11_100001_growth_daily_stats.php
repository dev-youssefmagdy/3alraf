<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pre-aggregated growth metrics (N1). scope=order: product_id 0, counts per channel/day; scope=product: line figures per product/channel/day.
        Schema::create('growth_daily_stats', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->date('stat_date');
            $t->string('scope', 10);
            $t->unsignedBigInteger('product_id')->default(0);
            $t->string('channel', 20);
            $t->unsignedInteger('orders_placed')->default(0);
            $t->unsignedInteger('orders_completed')->default(0);
            $t->unsignedInteger('orders_cancelled')->default(0);
            $t->unsignedInteger('orders_returned')->default(0);
            $t->unsignedInteger('units')->default(0);
            $t->decimal('revenue', 16, 2)->default(0);
            $t->decimal('cogs', 16, 2)->default(0);
            $t->decimal('other_costs', 16, 2)->default(0);
            $t->decimal('contribution_margin', 16, 2)->default(0);
            $t->unsignedInteger('missing_cogs')->default(0);
            $t->unsignedInteger('missing_shipping')->default(0);
            $t->timestamps();
            $t->unique(['tenant_id', 'stat_date', 'scope', 'product_id', 'channel'], 'growth_stats_unique');
            $t->index(['tenant_id', 'scope', 'stat_date'], 'growth_stats_scope_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_daily_stats');
    }
};
