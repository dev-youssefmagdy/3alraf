<?php

/**
 * n8n Growth system guardrails (docs/plans/n8n-growth-system.md). Defaults here; tenants override a whitelisted subset
 * (see Growth\GrowthConfig::OVERRIDABLE) from the admin Growth settings page. Money rules stay in Laravel, never in n8n.
 */
return [
    // Platform default of the per-tenant `growth` feature flag (tenants.settings.growth.enabled overrides).
    'enabled_default' => env('GROWTH_ENABLED_DEFAULT', false),

    // Scopes an integration client may hold. There is deliberately NO scope for prices, refunds, stock or ad budgets.
    'scopes' => [
        'read' => 'Read catalog / metrics / orders (aggregate, no PII)',
        'events:read' => 'Read the event log',
        'proposals:write' => 'Create approval proposals (never executed without a human)',
        'ingest:write' => 'Push interactions / ad results / classifications',
    ],
    // Any requested scope containing one of these words is refused at creation.
    'forbidden_scope_words' => ['price', 'refund', 'stock', 'budget', 'payout', 'discount'],

    'hmac' => ['replay_window_seconds' => 300, 'header' => 'X-Signature', 'timestamp_header' => 'X-Timestamp'],
    'throttle_per_minute' => 120,
    'idempotency_ttl_days' => 7,

    'events' => [
        'max_attempts' => 6,
        'backoff_seconds' => [60, 300, 900, 3600, 21600],
        'timeout_seconds' => 10,
        'retention_days' => 60,
        // Webhook URLs must be public https (http allowed only when false); IP literals in private ranges are always refused.
        'require_https' => env('GROWTH_WEBHOOK_HTTPS', true),
    ],

    'approvals' => ['ttl_days' => 7],

    // Guardrails (tenant-overridable). Percentages are 0-100.
    'guardrails' => [
        'min_margin_percent' => 15,
        'max_discount_percent' => 30,
        'low_stock_threshold' => 5,
        'min_days_of_cover' => 14,
        'max_days_of_cover' => 90,
        'reorder_cash_cap' => 50000,
        'ad_daily_budget_cap' => 500,
        'ad_total_budget_cap' => 5000,
        'auto_reply_allowlist' => [], // FAQ keys that may be answered automatically (N4); empty = nothing automatic
    ],

    // Personal data never leaves Laravel unmasked (events carry ids and amounts only).
    'pii' => [
        'mask_fields' => ['customer_name', 'customer_phone', 'customer_email', 'address', 'phone', 'email', 'name'],
        'mask_patterns' => ['email' => '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', 'phone' => '/\+?\d[\d\s\-]{7,}\d/'],
    ],
];
