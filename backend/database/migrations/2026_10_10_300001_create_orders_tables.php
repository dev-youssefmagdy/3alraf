<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function tenant(Blueprint $t): void
    {
        $t->id();
        $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    }

    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('label', 60)->nullable();
            $t->string('name');
            $t->string('phone', 30);
            $t->foreignId('city_id')->constrained()->restrictOnDelete();
            $t->string('address');
            $t->string('notes')->nullable();
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });

        Schema::create('carts', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('token', 64)->nullable()->index();
            $t->timestamps();
        });
        Schema::create('cart_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('product_variant_id')->default(0);
            $t->unsignedInteger('quantity');
            $t->timestamps();
            $t->unique(['cart_id', 'product_id', 'product_variant_id'], 'cart_items_unique');
        });

        Schema::create('discount_codes', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('code', 50);
            $t->string('type', 10);                       // percent|fixed
            $t->decimal('value', 14, 2)->default(0);
            $t->decimal('min_order', 14, 2)->default(0);
            $t->decimal('max_discount', 14, 2)->nullable();
            $t->unsignedInteger('usage_limit')->nullable();
            $t->unsignedInteger('per_customer_limit')->nullable();
            $t->unsignedInteger('used_count')->default(0);
            $t->dateTime('starts_at')->nullable();
            $t->dateTime('ends_at')->nullable();
            $t->string('scope', 12)->default('all');      // all|products|categories
            $t->json('product_ids')->nullable();
            $t->json('category_ids')->nullable();
            $t->boolean('free_shipping')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'code']);
        });

        Schema::create('flash_sales', function (Blueprint $t) {
            $this->tenant($t);
            $t->json('name');
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('flash_sale_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('flash_sale_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('product_variant_id')->default(0);
            $t->string('discount_type', 10);              // price|percent
            $t->decimal('value', 14, 2);
            $t->unsignedInteger('qty_limit')->nullable();
            $t->unsignedInteger('sold')->default(0);
            $t->timestamps();
            $t->unique(['flash_sale_id', 'product_id', 'product_variant_id'], 'flash_items_unique');
        });

        Schema::create('orders', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('number', 30)->nullable();
            $t->uuid('public_token')->unique();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('customer_name');
            $t->string('customer_phone', 30);
            $t->string('customer_email')->nullable();
            $t->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $t->string('address');
            $t->string('notes')->nullable();
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('shipping_company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('discount_code_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 20)->default('pending')->index();
            $t->string('payment_status', 20)->default('unpaid')->index();
            $t->string('payment_proof')->nullable();
            $t->string('proof_status', 10)->nullable();   // pending|approved|rejected
            $t->string('gateway_reference')->nullable();
            $t->decimal('subtotal', 14, 2);
            $t->decimal('discount', 14, 2)->default(0);
            $t->decimal('shipping', 14, 2)->default(0);
            $t->decimal('shipping_discount', 14, 2)->default(0);
            $t->decimal('shipping_net', 14, 2)->default(0);
            $t->decimal('shipping_cost', 14, 2)->default(0);
            $t->decimal('tax', 14, 2)->default(0);
            $t->decimal('payment_fee', 14, 2)->default(0);
            $t->decimal('payment_fee_charged', 14, 2)->default(0);
            $t->decimal('total', 14, 2);
            $t->decimal('paid_amount', 14, 2)->default(0);
            $t->decimal('refunded_amount', 14, 2)->default(0);
            $t->decimal('cogs', 14, 2)->default(0);
            $t->decimal('affiliate_commission', 14, 2)->default(0);
            $t->decimal('net_profit', 14, 2)->default(0);
            $t->string('currency', 8)->nullable();
            $t->boolean('stock_deducted')->default(false);
            $t->timestamp('placed_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'number']);
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('product_variant_id')->default(0);
            $t->unsignedBigInteger('flash_sale_item_id')->nullable();
            $t->string('name');
            $t->string('sku')->nullable();
            $t->string('barcode')->nullable();
            $t->decimal('unit_price', 14, 2);
            $t->decimal('unit_cost', 14, 2)->default(0);
            $t->unsignedInteger('quantity');
            $t->decimal('line_subtotal', 14, 2);
            $t->decimal('line_discount', 14, 2)->default(0);
            $t->unsignedInteger('returned_qty')->default(0);
            $t->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20);
            $t->string('note')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('discount_code_usages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('discount_code_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('customer_id')->nullable()->index();
            $t->decimal('amount', 14, 2);
            $t->timestamps();
        });

        Schema::create('order_returns', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('number', 30)->nullable();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('status', 12)->default('requested')->index();   // requested|approved|rejected|refunded
            $t->string('reason')->nullable();
            $t->boolean('restock')->default(true);
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('refund_shipping')->default(false);
            $t->decimal('return_value', 14, 2)->default(0);
            $t->decimal('refund_amount', 14, 2)->default(0);
            $t->decimal('return_shipping_cost', 14, 2)->default(0);
            $t->decimal('returned_cogs', 14, 2)->default(0);
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('refunded_at')->nullable();
            $t->timestamps();
        });
        Schema::create('order_return_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_return_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('quantity');
            $t->decimal('return_value', 14, 2);
            $t->timestamps();
        });

        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'order_return_items', 'order_returns', 'discount_code_usages', 'order_status_histories', 'order_items', 'orders', 'flash_sale_items', 'flash_sales', 'discount_codes', 'cart_items', 'carts', 'customer_addresses'] as $n) {
            Schema::dropIfExists($n);
        }
    }
};
