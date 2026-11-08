<?php

/**
 * Permission matrix seeded by PermissionSeeder: guard => module => actions. Names are "{module}.{action}".
 * One guard (`admin`). Append modules here and re-run `php artisan db:seed --class=PermissionSeeder`.
 */
$crud = ['view', 'create', 'edit', 'delete'];

return [
    'admin' => [
        'admins' => $crud,
        'roles' => $crud,
        'settings' => ['view', 'edit'],
        'whatsapp' => ['view', 'manage'],
        'whatsapp.chat' => ['view', 'send', 'manage', 'media'],
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
        // ERP / accounting
        'erp.dashboard' => ['view'],
        'erp.accounts' => ['view', 'manage'],
        'erp.journals' => ['view', 'create', 'post', 'reverse'],
        'erp.expenses' => ['manage'],
        'erp.reports' => ['view', 'export'],
        'erp.profits' => ['view', 'distribute'],
        'erp.owners' => ['view', 'manage'],
        'erp.branches' => ['view'],
        'erp.settings' => ['manage'],
        'erp.periods' => ['view', 'close', 'reopen'],
        'erp.assets' => ['view', 'manage'],
        'erp.audit' => ['view'],
        'returns' => ['view', 'create', 'approve', 'refund'],
        'customers' => ['view'],
        'discount_codes' => $crud,
        'flash_sales' => $crud,
        'bundles' => $crud,
        'packaging' => $crud,
        // Phase 5 marketing (lang group `growth` = affiliates / reviews / pages, not the removed n8n system)
        'affiliates' => ['view', 'edit', 'delete'],
        'affiliate_tiers' => $crud,
        'affiliate_payouts' => ['view', 'edit'],
        'product_lists' => ['view', 'edit'],
        'reviews' => ['view', 'edit', 'delete'],
        'stock_requests' => ['view', 'edit', 'delete'],
        'custom_pages' => $crud,
        'static_pages' => $crud,
        'blog_posts' => $crud,
        // Marketer panel (/marketer, MARKETER_PLAN.md P4): Gift Finder admin + n8n integration clients live there now.
        'gift_finder' => ['view', 'manage'],
        'integration_clients' => ['view', 'manage'],
        'marketing' => ['view', 'analytics', 'tokens.manage', 'audit.view'],          // panel dashboard, analytics, API tokens, API request log
        'ai_suggestions' => ['view', 'create', 'edit', 'approve', 'reject', 'apply', 'archive'], // P5 Suggestions Center (approve/apply = dashboard only)
        'ai_media' => ['view', 'upload', 'edit', 'delete', 'approve'],                 // P6 private social-media library (approve = library item review)
        'ai_seo' => ['approve'],
        'ai_blog' => ['approve'],
        'ai_theme' => ['approve'],
        'meta_ads' => ['view', 'draft', 'approve', 'publish'],                         // P6 Meta campaign drafts (publish = always PAUSED)
        // master catalog: products, categories, brands, tags, attributes
        'products' => $crud, // products + selling fields + activate-many + QR (merged former catalog.*)
        'categories' => $crud,
        'brands' => $crud,
        'tags' => $crud,
        'attributes' => $crud,
        // settings: Settings → General (name, country, currency, locales, timezone, Gift Finder / customer-cancel switches) + languages
        'general_settings' => ['view', 'edit'],
        'languages' => $crud,
    ],
];
