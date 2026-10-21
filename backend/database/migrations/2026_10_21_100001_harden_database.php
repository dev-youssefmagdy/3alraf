<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** GAP_ANALYSIS §5: tenant-leading indexes, CHECK constraints, missing product FKs, wider cost decimals, restrictive orders.branch_id. */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Indexes (tenant first) ----
        Schema::table('orders', function (Blueprint $t) {
            $t->index(['tenant_id', 'status', 'created_at'], 'orders_tenant_status_created');
            $t->index(['tenant_id', 'created_at'], 'orders_tenant_created');
            $t->index(['tenant_id', 'customer_id'], 'orders_tenant_customer');
        });
        Schema::table('tenant_products', fn (Blueprint $t) => $t->index(['tenant_id', 'is_active', 'id'], 'tp_tenant_active_id'));
        Schema::table('stock_movements', fn (Blueprint $t) => $t->index(['tenant_id', 'product_id', 'product_variant_id', 'created_at'], 'sm_tenant_product_created'));
        Schema::table('stock_levels', fn (Blueprint $t) => $t->index(['tenant_id', 'product_id', 'product_variant_id'], 'sl_tenant_product'));
        Schema::table('order_items', function (Blueprint $t) {
            $t->index(['tenant_id', 'product_id'], 'oi_tenant_product');
            $t->index(['order_id', 'product_id'], 'oi_order_product');
        });
        Schema::table('reviews', fn (Blueprint $t) => $t->index(['tenant_id', 'status', 'product_id'], 'reviews_tenant_status_product'));
        Schema::table('discount_code_usages', function (Blueprint $t) {
            $t->index(['discount_code_id', 'customer_id'], 'dcu_code_customer');
            $t->unique(['order_id', 'discount_code_id'], 'dcu_order_code_unique');
        });
        Schema::table('carts', fn (Blueprint $t) => $t->index(['tenant_id', 'token'], 'carts_tenant_token'));

        // ---- Missing product FKs (orphans removed first) ----
        foreach (['reviews' => false, 'wishlist_items' => false, 'product_list_items' => false, 'custom_page_products' => false, 'affiliate_clicks' => true] as $table => $nullable) {
            if ($nullable) {
                DB::table($table)->whereNotNull('product_id')->whereNotIn('product_id', DB::table('products')->select('id'))->update(['product_id' => null]);
            } else {
                DB::table($table)->whereNotIn('product_id', DB::table('products')->select('id'))->delete();
            }
            Schema::table($table, function (Blueprint $t) use ($nullable) {
                $fk = $t->foreign('product_id')->references('id')->on('products');
                $nullable ? $fk->nullOnDelete() : $fk->cascadeOnDelete();
            });
        }

        // ---- orders.branch_id: a branch with orders can never be deleted ----
        Schema::table('orders', function (Blueprint $t) {
            $t->dropForeign(['branch_id']);
        });
        Schema::table('orders', fn (Blueprint $t) => $t->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete());

        // ---- Costs: decimal(14,4) ----
        foreach ([
            'stock_levels' => ['avg_cost' => 'default'], 'stock_movements' => ['unit_cost' => 'default', 'avg_cost_after' => 'default'],
            'order_items' => ['unit_cost' => 'default'], 'purchase_order_items' => ['unit_cost' => 'plain'],
            'stock_transfer_items' => ['unit_cost' => 'default'], 'stock_adjustment_items' => ['unit_cost' => 'null'],
            'tenant_products' => ['cost' => 'default'], 'tenant_product_variants' => ['cost' => 'null'],
        ] as $table => $cols) {
            Schema::table($table, function (Blueprint $t) use ($cols) {
                foreach ($cols as $col => $mode) {
                    $c = $t->decimal($col, 14, 4);
                    match ($mode) {
                        'null' => $c->nullable()->change(),
                        'default' => $c->default(0)->change(),
                        default => $c->change(),
                    };
                }
            });
        }

        // ---- CHECK constraints ----
        DB::statement('ALTER TABLE stock_levels ADD CONSTRAINT chk_stock_levels_reserved CHECK (reserved >= 0)');
        DB::statement('ALTER TABLE flash_sale_items ADD CONSTRAINT chk_flash_items_sold CHECK (qty_limit IS NULL OR sold <= qty_limit)');
        DB::statement('ALTER TABLE discount_codes ADD CONSTRAINT chk_discount_used CHECK (usage_limit IS NULL OR used_count <= usage_limit)');
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE stock_levels DROP CHECK chk_stock_levels_reserved');
        DB::statement('ALTER TABLE flash_sale_items DROP CHECK chk_flash_items_sold');
        DB::statement('ALTER TABLE discount_codes DROP CHECK chk_discount_used');
        DB::statement('ALTER TABLE reviews DROP CHECK chk_reviews_rating');

        Schema::table('orders', function (Blueprint $t) {
            $t->dropForeign(['branch_id']);
            $t->dropIndex('orders_tenant_status_created');
            $t->dropIndex('orders_tenant_created');
            $t->dropIndex('orders_tenant_customer');
        });
        Schema::table('orders', fn (Blueprint $t) => $t->foreign('branch_id')->references('id')->on('branches')->nullOnDelete());
        foreach (['reviews', 'wishlist_items', 'product_list_items', 'custom_page_products', 'affiliate_clicks'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign(['product_id']));
        }
        Schema::table('tenant_products', fn (Blueprint $t) => $t->dropIndex('tp_tenant_active_id'));
        Schema::table('stock_movements', fn (Blueprint $t) => $t->dropIndex('sm_tenant_product_created'));
        Schema::table('stock_levels', fn (Blueprint $t) => $t->dropIndex('sl_tenant_product'));
        Schema::table('order_items', function (Blueprint $t) {
            $t->dropIndex('oi_tenant_product');
            $t->dropIndex('oi_order_product');
        });
        Schema::table('reviews', fn (Blueprint $t) => $t->dropIndex('reviews_tenant_status_product'));
        Schema::table('discount_code_usages', function (Blueprint $t) {
            $t->dropUnique('dcu_order_code_unique');
            $t->dropIndex('dcu_code_customer');
        });
        Schema::table('carts', fn (Blueprint $t) => $t->dropIndex('carts_tenant_token'));
        // cost decimals intentionally stay (14,4): narrowing would lose data.
    }
};
