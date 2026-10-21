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
        Schema::create('cities', function (Blueprint $t) {
            $this->tenant($t);
            $t->json('name');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('branches', function (Blueprint $t) {
            $this->tenant($t);
            $t->json('name');
            $t->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $t->string('address')->nullable();
            $t->string('phone', 30)->nullable();
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('suppliers', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->string('contact_name')->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('email')->nullable();
            $t->string('address')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('shipping_companies', function (Blueprint $t) {
            $this->tenant($t);
            $t->json('name');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('shipping_rates', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('shipping_company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('city_id')->constrained()->cascadeOnDelete();
            $t->decimal('price', 14, 2);
            $t->decimal('cost', 14, 2)->nullable();      // what we pay the carrier (null = price)
            $t->decimal('free_over', 14, 2)->nullable();
            $t->unsignedSmallInteger('eta_min_days')->nullable();
            $t->unsignedSmallInteger('eta_max_days')->nullable();
            $t->boolean('supports_cod')->default(true);
            $t->timestamps();
            $t->unique(['shipping_company_id', 'city_id']);
        });

        Schema::create('payment_methods', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('type', 10);                       // cod|wallet|online
            $t->string('provider', 30);
            $t->json('name');
            $t->json('instructions')->nullable();
            $t->string('account_number')->nullable();     // wallet receiving number
            $t->boolean('requires_proof')->default(false);
            $t->decimal('fee_percent', 6, 3)->default(0);
            $t->decimal('fee_fixed', 14, 2)->default(0);
            $t->string('fee_bearer', 10)->default('customer');
            $t->text('credentials')->nullable();         // encrypted:array
            $t->boolean('is_sandbox')->default(true);
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('banners', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('placement', 30);
            $t->json('title')->nullable();
            $t->json('images')->nullable();               // {locale: path}
            $t->string('link_url')->nullable();
            $t->dateTime('starts_at')->nullable();
            $t->dateTime('ends_at')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $t) {
            $this->tenant($t);
            $t->json('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('expenses', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('amount', 14, 2);
            $t->date('expense_date');
            $t->text('note')->nullable();
            $t->string('attachment')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'expense_date']);
        });

        // ---- Stock ----
        Schema::create('stock_levels', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('product_variant_id')->default(0); // 0 = simple product
            $t->integer('on_hand')->default(0);
            $t->integer('reserved')->default(0);
            $t->decimal('avg_cost', 14, 2)->default(0);
            $t->timestamps();
            $t->unique(['branch_id', 'product_id', 'product_variant_id'], 'stock_levels_unique');
        });

        Schema::create('stock_movements', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('product_variant_id')->default(0);
            $t->string('type', 20);
            $t->integer('quantity');                      // signed
            $t->decimal('unit_cost', 14, 2)->default(0);
            $t->integer('balance_after');
            $t->decimal('avg_cost_after', 14, 2)->default(0);
            $t->nullableMorphs('reference');
            $t->string('note')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tenant_id', 'created_at']);
        });

        Schema::create('purchase_orders', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('number', 30)->nullable();
            $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->string('status', 12)->default('draft');   // draft|received|cancelled
            $t->decimal('items_total', 14, 2)->default(0);
            $t->decimal('shipping_cost', 14, 2)->default(0);
            $t->decimal('other_cost', 14, 2)->default(0);
            $t->decimal('total_cost', 14, 2)->default(0);
            $t->date('ordered_at');
            $t->dateTime('received_at')->nullable();
            $t->text('note')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'number']);
        });

        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('product_variant_id')->default(0);
            $t->unsignedInteger('quantity');
            $t->decimal('unit_cost', 14, 2);
            $t->decimal('line_total', 14, 2);
        });

        Schema::create('stock_transfers', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('number', 30)->nullable();
            $t->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $t->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
            $t->string('status', 12)->default('draft');   // draft|in_transit|received|cancelled
            $t->text('note')->nullable();
            $t->dateTime('sent_at')->nullable();
            $t->dateTime('received_at')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'number']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('product_variant_id')->default(0);
            $t->unsignedInteger('quantity');
            $t->decimal('unit_cost', 14, 2)->default(0);  // snapshot of source avg cost at send
        });

        Schema::create('stock_adjustments', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('number', 30)->nullable();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->string('type', 10);                       // increase|decrease
            $t->string('reason', 20);
            $t->text('note')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'number']);
        });

        Schema::create('stock_adjustment_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('product_variant_id')->default(0);
            $t->unsignedInteger('quantity');
            $t->decimal('unit_cost', 14, 2)->nullable();
        });
    }

    public function down(): void
    {
        foreach (['stock_adjustment_items', 'stock_adjustments', 'stock_transfer_items', 'stock_transfers', 'purchase_order_items', 'purchase_orders', 'stock_movements', 'stock_levels', 'expenses', 'expense_categories', 'banners', 'payment_methods', 'shipping_rates', 'shipping_companies', 'suppliers', 'branches', 'cities'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
