<?php

/**
 * n8n Growth system guardrails (docs/plans/n8n-growth-system.md). Defaults here; tenants override a whitelisted subset
 * (see Growth\GrowthConfig::OVERRIDABLE) from the admin Growth settings page. Money rules stay in Laravel, never in n8n.
 */
return [
    // Platform default of the per-tenant `growth` feature flag (tenants.settings.growth.enabled overrides).
    'enabled_default' => env('GROWTH_ENABLED_DEFAULT', false),

    // Per-feature switches (plan section 9). A feature is active only if the master flag is on, its own switch is on
    // (tenants.settings.growth.features.{key}, default OFF) and every depends_on feature is active. built=false = not yet implemented (hidden in UI).
    'features' => [
        'integration_api' => ['ar' => 'واجهة التكامل (n8n)', 'en' => 'Integration API (n8n)', 'group' => 'foundation', 'depends_on' => [], 'built' => true],
        'events_outbox' => ['ar' => 'إرسال الأحداث (Webhooks)', 'en' => 'Event outbox (webhooks)', 'group' => 'foundation', 'depends_on' => ['integration_api'], 'built' => true],
        'approvals' => ['ar' => 'طابور الموافقات', 'en' => 'Approval queue', 'group' => 'foundation', 'depends_on' => [], 'built' => true],
        'experiments' => ['ar' => 'التجارب', 'en' => 'Experiments', 'group' => 'foundation', 'depends_on' => [], 'built' => true],
        'brief' => ['ar' => 'تقرير المالك اليومي', 'en' => 'Owner brief', 'group' => 'insight', 'depends_on' => [], 'built' => true],
        'brand_memory' => ['ar' => 'ذاكرة العلامة', 'en' => 'Brand memory', 'group' => 'content', 'depends_on' => [], 'built' => true],
        'truth_guard' => ['ar' => 'حارس صدق المنتج', 'en' => 'Product truth guard', 'group' => 'content', 'depends_on' => ['brand_memory'], 'built' => true],
        'content_factory' => ['ar' => 'مصنع المحتوى', 'en' => 'Content factory', 'group' => 'content', 'depends_on' => ['brand_memory'], 'built' => true],
        'bundle_engine' => ['ar' => 'محرك الباندل', 'en' => 'Bundle engine', 'group' => 'offers', 'depends_on' => [], 'built' => true],
        'bundle_ab' => ['ar' => 'اختبار الباندل A/B', 'en' => 'Bundle A/B', 'group' => 'offers', 'depends_on' => ['bundle_engine', 'experiments'], 'built' => true],
        'seasonal_calendar' => ['ar' => 'التقويم الموسمي', 'en' => 'Seasonal calendar', 'group' => 'offers', 'depends_on' => [], 'built' => true],
        'gift_finder' => ['ar' => 'باحث الهدايا', 'en' => 'Gift finder', 'group' => 'offers', 'depends_on' => [], 'built' => true],
        'interactions' => ['ar' => 'المحادثات والتفاعلات', 'en' => 'Interactions', 'group' => 'engagement', 'depends_on' => ['integration_api'], 'built' => true],
        'voc' => ['ar' => 'صوت العميل', 'en' => 'Voice of customer', 'group' => 'engagement', 'depends_on' => ['interactions'], 'built' => true],
        'reputation_radar' => ['ar' => 'رادار السمعة', 'en' => 'Reputation radar', 'group' => 'engagement', 'depends_on' => ['interactions'], 'built' => true],
        'comment_to_cart' => ['ar' => 'من التعليق للسلة', 'en' => 'Comment to cart', 'group' => 'engagement', 'depends_on' => ['interactions'], 'built' => true],
        'auto_reply' => ['ar' => 'الرد الآلي', 'en' => 'Auto reply', 'group' => 'engagement', 'depends_on' => ['interactions'], 'built' => true],
        'missed_opportunities' => ['ar' => 'الفرص الضائعة', 'en' => 'Missed opportunities', 'group' => 'engagement', 'depends_on' => ['interactions'], 'built' => true],
        'experiment_lab' => ['ar' => 'معمل التجارب', 'en' => 'Experiment lab', 'group' => 'growth', 'depends_on' => ['experiments'], 'built' => true],
        'small_batch_lab' => ['ar' => 'معمل الدفعات الصغيرة', 'en' => 'Small-batch lab', 'group' => 'growth', 'depends_on' => [], 'built' => true],
        'ambassadors' => ['ar' => 'السفراء', 'en' => 'Ambassadors', 'group' => 'growth', 'depends_on' => [], 'built' => true],
        'inventory_engine' => ['ar' => 'محرك المخزون', 'en' => 'Inventory engine', 'group' => 'operations', 'depends_on' => [], 'built' => true],
        'return_intel' => ['ar' => 'ذكاء المرتجعات', 'en' => 'Return intelligence', 'group' => 'operations', 'depends_on' => [], 'built' => true],
        'trend_radar' => ['ar' => 'رادار الترند', 'en' => 'Trend radar', 'group' => 'operations', 'depends_on' => [], 'built' => true],
        'ceo_challenge' => ['ar' => 'تحدي المدير', 'en' => 'CEO challenge', 'group' => 'operations', 'depends_on' => [], 'built' => true],
    ],

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

    // N5 lab thresholds: no stop/scale/revise advice below these minimums (a few days or clicks is noise, not evidence).
    'lab' => [
        'min_days' => 7, 'min_clicks' => 100, 'min_orders' => 10, 'max_budget_increase_percent' => 30,
        'scale_cm_ratio' => 0.5, 'fatigue_min_impressions' => 1000, 'fatigue_ctr_drop_percent' => 25, 'fatigue_recent_days' => 3, 'fatigue_baseline_days' => 7,
        'ambassador_min_orders_for_report' => 1, 'smallbatch_min_days' => 3,
    ],

    // N6 decision engine / trend radar / CEO challenge. Assumptions are labelled as such in the UI.
    'capital' => [
        'horizon_days' => 30, 'promo_uplift' => 1.5, 'promo_baseline_sell_through' => 0.1, 'abc_a_share' => 80, 'abc_b_share' => 95,
        'story_min_questions' => 3, 'story_max_sold_30d' => 3, 'season_hold_days' => 60, 'expiry_urgent_share' => 0.7, 'min_history_days' => 14,
    ],
    'returns' => ['recurring_min' => 3, 'window_days' => 30],
    'trend' => ['weights' => ['demand' => 25, 'margin' => 25, 'supplier' => 15, 'audience' => 15, 'risk' => 10, 'content' => 10], 'min_score' => 40, 'max_candidates' => 15],
    'ceo' => ['cooldown_weeks' => 4, 'duration_days' => 14],

    // Personal data never leaves Laravel unmasked (events carry ids and amounts only).
    'pii' => [
        'mask_fields' => ['customer_name', 'customer_phone', 'customer_email', 'address', 'phone', 'email', 'name'],
        'mask_patterns' => ['email' => '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', 'phone' => '/\+?\d[\d\s\-]{7,}\d/'],
    ],
];
