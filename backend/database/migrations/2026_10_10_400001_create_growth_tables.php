<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->uuid('uuid')->unique();
            $t->string('name');
            $t->string('email');
            $t->string('phone', 30)->nullable();
            $t->string('password');
            $t->string('status', 12)->default('pending')->index(); // pending|approved|suspended
            $t->timestamp('approved_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
            $t->unique(['tenant_id', 'email']);
        });

        Schema::create('affiliate_tiers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->decimal('min_sales', 14, 2)->default(0);
            $t->decimal('percent', 5, 2);
            $t->timestamps();
            $t->unique(['tenant_id', 'min_sales']);
        });

        Schema::table('orders', function (Blueprint $t) {
            $t->unsignedBigInteger('affiliate_id')->nullable()->after('discount_code_id')->index();
        });

        Schema::create('affiliate_clicks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['affiliate_id', 'created_at']);
        });

        Schema::create('affiliate_payouts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 14, 2);
            $t->string('status', 12)->default('requested')->index(); // requested|paid|rejected
            $t->string('note')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        Schema::create('affiliate_commissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('payout_id')->nullable()->constrained('affiliate_payouts')->nullOnDelete();
            $t->decimal('base', 14, 2);
            $t->decimal('percent', 5, 2);
            $t->decimal('amount', 14, 2);
            $t->string('status', 12)->default('pending')->index(); // pending|approved|paid|voided
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->unique('order_id');
        });

        Schema::create('product_list_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('list_type', 20);
            $t->unsignedBigInteger('product_id');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->unique(['tenant_id', 'list_type', 'product_id'], 'pli_unique');
        });

        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('product_id')->index();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedTinyInteger('rating');
            $t->text('body')->nullable();
            $t->string('status', 10)->default('pending')->index(); // pending|approved|rejected
            $t->timestamps();
            $t->unique(['tenant_id', 'product_id', 'customer_id']);
        });

        Schema::create('custom_pages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('slug', 100);
            $t->json('title');
            $t->json('filters')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'slug']);
        });

        Schema::create('custom_page_products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('custom_page_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('product_id');
            $t->unsignedInteger('sort_order')->default(0);
            $t->unique(['custom_page_id', 'product_id']);
        });

        Schema::create('static_pages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('slug', 100);
            $t->json('title');
            $t->json('content')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'slug']);
        });

        Schema::create('wishlist_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('product_id');
            $t->timestamps();
            $t->unique(['customer_id', 'product_id']);
        });
    }

    public function down(): void
    {
        foreach (['wishlist_items', 'static_pages', 'custom_page_products', 'custom_pages', 'reviews', 'product_list_items', 'affiliate_commissions', 'affiliate_payouts', 'affiliate_clicks'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('affiliate_id'));
        Schema::dropIfExists('affiliate_tiers');
        Schema::dropIfExists('affiliates');
    }
};
