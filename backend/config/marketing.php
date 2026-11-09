<?php

/**
 * Marketer panel (/marketer) + Marketing internal API (/api/internal/marketing, routes/api_marketing.php) — MARKETER_PLAN.md P4.
 * The API is read-only in P4; it is consumed by the MCP server (P7) with a service token managed in the marketer panel.
 * Docs: docs/marketing/API_DOCUMENTATION.md, docs/marketing/MARKETER_PANEL.md.
 */
return [
    // Token scopes. `enabled=false` scopes are reserved (shown, not grantable) until the phase that needs them ships.
    'scopes' => [
        'marketing:read' => ['enabled' => true],
        // One per suggestion type: create / edit own pending suggestions of that type, submit for review (never approve / apply)
        'suggestions:write:product' => ['enabled' => true],
        'suggestions:write:bundle' => ['enabled' => true],
        'suggestions:write:slider' => ['enabled' => true],
        'suggestions:write:offer' => ['enabled' => true],
        'suggestions:write:coupon' => ['enabled' => true],
        'suggestions:write:seo' => ['enabled' => true],
        'suggestions:write:blog' => ['enabled' => true],
        'suggestions:write:seasonal_theme' => ['enabled' => true],
        'suggestions:write:social_content' => ['enabled' => true],
        'suggestions:write:media_asset' => ['enabled' => true],
        'meta:read' => ['enabled' => true],         // P6: Meta ad accounts / campaigns / ad sets / ads / insights (cached reads) + campaign drafts (read)
        'meta:draft' => ['enabled' => true],        // P6: create / edit own Meta campaign drafts, submit for approval (never approve / publish)
    ],

    // Plain token = "{token_prefix}.{secret}" (prefix starts with `mkt_`); only sha256(plain token) is stored.
    'token_prefix' => 'mkt_',

    // Expiry choices offered by the panel (days; null = never). Default = 90.
    'token_expiry_days' => [30, 90, 180, 365, null],
    'token_default_expiry_days' => 90,

    // Per-token limit (requests per minute) after authentication.
    'throttle_per_minute' => (int) env('MARKETING_API_THROTTLE', 120),

    // marketing_api_requests (token, method, route name, status, duration; no body, no IP, no PII) kept for N days (marketing:prune-requests).
    'request_log_days' => 30,

    // Pagination + date range rules of every read endpoint.
    'per_page_default' => 25,
    'per_page_max' => 100,
    'max_range_days' => 366,
    'default_range_days' => 30,

    // Slow movers: active, in stock, and at most this many units sold in the range.
    'slow_moving_max_units' => 2,

    // Campaign candidates score (0..100) = Σ weight × component (each component 0..1). See App\Services\Marketing\CampaignScorer.
    'campaign' => [
        'weights' => ['velocity' => 35, 'margin' => 30, 'stock_cover' => 20, 'recency' => 15],
        'margin_full_percent' => 50,     // gross margin % that earns the full margin component
        'stock_cover_full_days' => 60,   // days of stock cover (available / daily velocity) that earn the full stock-cover component
        'stock_cover_no_sales_units' => 10, // with no sales in the range: available >= this many units = full cover
        'recency_full_days' => 7,        // last sale within N days = full recency; decays linearly to 0 at the range length
        'min_available' => 1,            // candidates must have at least this many units available (or backorder allowed)
    ],

    // Suggestions Center (P5; docs/marketing/APPROVAL_AND_EXECUTION_ENGINE.md)
    'suggestions' => [
        'default_expiry_days' => 60,          // a suggestion without expires_at expires after N days (marketing:expire-suggestions, daily)
        'approval_valid_hours' => 168,        // an approval must be used (applied) within N hours, else re-approve
        'rejected_cooldown_days' => 30,       // the same rejected suggestion (type + target + payload hash) cannot be re-created for N days without force_reason
        'min_margin_percent' => 10,           // bundle margin below this ⇒ warning (negative ⇒ refused)
        'theme_preview_minutes' => 60,        // signed storefront preview link lifetime
        'source_data_max_bytes' => 20000,     // max size of source_data (references the AI used) per suggestion
        'per_page_max' => 100,
    ],

    // Private product social-media library (P6, MCP.md §21; docs/marketing/SOCIAL_MEDIA_LIBRARY.md). Files live on media.private_disk only.
    'media' => [
        'dir' => 'social-media',                         // root folder on the private disk
        'image_max_kb' => (int) env('SOCIAL_MEDIA_IMAGE_MAX_KB', 15360),   // 15 MB
        'video_max_kb' => (int) env('SOCIAL_MEDIA_VIDEO_MAX_KB', 204800),  // 200 MB (PHP upload_max_filesize / post_max_size must allow it)
        'image_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'video_mimes' => ['video/mp4', 'video/webm', 'video/quicktime'],
        'max_dimension' => 10000,                        // px, images
        'min_dimension' => 50,
        'thumb_px' => 480,
        'signed_preview_minutes' => 10,                  // API preview URL lifetime
        'per_page' => 24,
    ],
];
