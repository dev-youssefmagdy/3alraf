<?php

/**
 * Permission matrix seeded by PermissionSeeder: guard => module => actions. Names are "{module}.{action}".
 * Later phases: append modules here and re-run `php artisan db:seed --class=PermissionSeeder`.
 */
$crud = ['view', 'create', 'edit', 'delete'];

return [
    'central' => [
        'languages' => $crud,
        'tenants' => $crud,
        'brands' => $crud,
        'audit' => ['view'],
        'tags' => $crud,
        'attributes' => $crud,
        'categories' => $crud,
        'products' => $crud,
        'admins' => $crud,
        'roles' => $crud,
        'reports' => ['view', 'export'],
        'ai_providers' => $crud,
        'ai_models' => $crud,
        'ai_tenants' => ['view', 'edit'],
        'ai_usage' => ['view'],
        'whatsapp' => ['view', 'manage'],
    ],
    'tenant' => [
        'admins' => $crud,
        'roles' => $crud,
        'catalog' => ['view', 'create', 'edit', 'delete'],
        'settings' => ['view', 'edit'],
        'whatsapp' => ['view', 'manage'],
        'audit' => ['view'],
        'reports' => ['view', 'export'],
        // Phase 3 operations
        'cities' => $crud,
        'branches' => $crud,
        'suppliers' => $crud,
        'shipping' => $crud,
        'payment_methods' => $crud,
        'banners' => $crud,
        'expense_categories' => $crud,
        'expenses' => $crud,
        'stock' => ['view'],
        'purchases' => ['view', 'create', 'edit', 'delete', 'receive'],
        'stock_transfers' => ['view', 'create', 'edit', 'delete', 'send', 'receive'],
        'stock_adjustments' => ['view', 'create'],
        // Phase 4 orders
        'orders' => ['view', 'edit', 'payments', 'email_notifications'],
        'pos' => ['use'],
        'returns' => ['view', 'create', 'approve', 'refund'],
        'customers' => ['view'],
        'discount_codes' => $crud,
        'flash_sales' => $crud,
        'bundles' => $crud,
        'packaging' => $crud,
        // Phase 5 growth
        'affiliates' => ['view', 'edit', 'delete'],
        'affiliate_tiers' => $crud,
        'affiliate_payouts' => ['view', 'edit'],
        'product_lists' => ['view', 'edit'],
        'reviews' => ['view', 'edit', 'delete'],
        'stock_requests' => ['view', 'edit', 'delete'],
        'custom_pages' => $crud,
        'static_pages' => $crud,
        'blog_posts' => $crud,
        // AI
        'ai_settings' => ['view', 'edit'],
        'ai_chat' => ['use'],
        'ai_trends' => ['view'],
        'ai_studio' => ['use'],
        'ai_editor' => ['use'],
        'ai_generators' => ['use', 'approve'],
        // n8n growth system (feature flag `growth`)
        'growth' => ['view', 'manage', 'approve'],
    ],
];
