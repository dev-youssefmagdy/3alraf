<?php

use App\Models\Branch;
use App\Models\Bundle;
use App\Models\PackagingOption;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingCompany;
use App\Models\Supplier;
use App\Models\Tag;

return [
    // Soft-deleted rows older than this many days are handled by `php artisan retention:purge` (daily).
    'soft_delete_days' => (int) env('SOFT_DELETE_RETENTION_DAYS', 60),

    // key => model, in PROCESSING ORDER (orders first: purging them frees customers, codes, methods, branches...).
    // Tenants are deliberately NOT here: a tenant owns ~50 tables, media dirs and QR files; purging one is an explicit,
    // manual operation, never automatic. Per-model rules: see App\Services\Retention\SoftDeletePurger and docs/PROGRESS.md.
    'models' => [
        'order' => Order::class,
        'customer' => Customer::class,
        'variant' => ProductVariant::class,
        'product' => Product::class,
        'bundle' => Bundle::class,
        'packaging_option' => PackagingOption::class,
        'brand' => Brand::class,
        'category' => Category::class,
        'tag' => Tag::class,
        'discount_code' => DiscountCode::class,
        'payment_method' => PaymentMethod::class,
        'shipping_company' => ShippingCompany::class,
        'supplier' => Supplier::class,
        'branch' => Branch::class,
        'city' => City::class,
    ],

    'chunk' => 100,
];
