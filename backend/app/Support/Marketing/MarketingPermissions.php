<?php

namespace App\Support\Marketing;

/**
 * Permissions and preset roles of the marketer panel (/marketer, guard `marketer`, accounts in `marketers`).
 * All names live in config/permissions.php under `marketer`; PermissionSeeder creates them and the two roles.
 *
 * Owner decision (2026-11-15, Q2): marketers create and edit; only `marketer-owner` approves, applies, publishes,
 * issues credentials and manages marketer accounts.
 */
final class MarketingPermissions
{
    public const GUARD = 'marketer';

    /** Preset role: day-to-day marketing work (create / edit / submit / archive). */
    public const ROLE = 'marketer';

    /** System role: every marketer permission; also bypasses every marketer gate (Gate::before). */
    public const OWNER_ROLE = 'marketer-owner';

    /** Every permission that opens something in the marketer panel (gate `marketing.panel` = any of these). */
    public const PANEL = [
        'marketing.view', 'marketing.analytics', 'marketing.tokens.manage', 'marketing.audit.view',
        'ai_suggestions.view', 'ai_suggestions.create', 'ai_suggestions.edit', 'ai_suggestions.approve', 'ai_suggestions.reject', 'ai_suggestions.apply', 'ai_suggestions.archive',
        'ai_media.view', 'ai_media.upload', 'ai_media.edit', 'ai_media.delete', 'ai_media.approve',
        'ai_casting.view', 'ai_casting.create', 'ai_casting.edit', 'ai_casting.delete', 'ai_casting.approve',
        'ai_products.approve', 'ai_seo.approve', 'ai_blog.approve', 'ai_theme.approve',
        'meta_ads.view', 'meta_ads.draft', 'meta_ads.approve', 'meta_ads.publish',
        'gift_finder.view', 'gift_finder.manage',
        'integration_clients.view', 'integration_clients.manage',
        'marketers.view', 'marketers.manage',
        ...self::TOOLS,
    ];

    /** Marketing tools shared with /admin (Q1), full CRUD on guard `marketer`. */
    public const TOOLS = [
        'banners.view', 'banners.create', 'banners.edit', 'banners.delete',
        'discount_codes.view', 'discount_codes.create', 'discount_codes.edit', 'discount_codes.delete',
        'flash_sales.view', 'flash_sales.create', 'flash_sales.edit', 'flash_sales.delete',
        'bundles.view', 'bundles.create', 'bundles.edit', 'bundles.delete',
        'blog_posts.view', 'blog_posts.create', 'blog_posts.edit', 'blog_posts.delete',
        'static_pages.view', 'static_pages.create', 'static_pages.edit', 'static_pages.delete',
        'custom_pages.view', 'custom_pages.create', 'custom_pages.edit', 'custom_pages.delete',
        'seo.view', 'seo.edit',
        'affiliates.view', 'affiliates.edit', 'affiliates.delete',
        'product_lists.view', 'product_lists.edit',
        'reviews.view', 'reviews.edit', 'reviews.delete',
    ];

    /** Preset role `marketer`: no approve / reject / apply / publish, no credentials, no account management. */
    public const MARKETER = [
        'marketing.view', 'marketing.analytics',
        'ai_suggestions.view', 'ai_suggestions.create', 'ai_suggestions.edit', 'ai_suggestions.archive',
        'ai_media.view', 'ai_media.upload', 'ai_media.edit', 'ai_media.delete',
        'ai_casting.view', 'ai_casting.create', 'ai_casting.edit',
        'meta_ads.view', 'meta_ads.draft',
        'gift_finder.view', 'gift_finder.manage',
        'integration_clients.view',
        ...self::TOOLS,
    ];
}
