<?php

return [
    // Subdomains that can never be a tenant slug.
    'reserved_subdomains' => ['www', 'admin', 'api', 'mail', 'ftp', 'static', 'cdn', 'app', 'central', 'assets'],

    // Top-level paths (tenant domain/{slug}) that custom pages cannot use. Extend as storefront routes are added.
    'reserved_slugs' => ['admin', 'login', 'logout', 'up', 'storage', 'build', 'api', 'ar', 'en', 'webhooks', 'product', 'page', 'affiliate', 'cart', 'checkout', 'account', 'order', 'payments', 'sitemap.xml', 'robots.txt', 'favicon.ico'],

    // Currency that central (cross-tenant) reports are normalized to: amount x tenants.currency_rate.
    'base_currency' => env('BASE_CURRENCY', 'EGP'),
];
