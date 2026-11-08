<?php

namespace App\Support\Marketing;

/**
 * Permission set of the marketer panel (/marketer) and the preset role `marketer` (MARKETER_PLAN.md P4, MCP.md §27).
 * All names live in config/permissions.php (guard `admin`); PermissionSeeder creates them and the role.
 */
final class MarketingPermissions
{
    public const ROLE = 'marketer';

    /** Every permission that opens something in the marketer panel (gate `marketing.panel` = any of these). */
    public const PANEL = [
        'marketing.view', 'marketing.analytics', 'marketing.tokens.manage', 'marketing.audit.view',
        'ai_suggestions.view', 'ai_suggestions.create', 'ai_suggestions.edit', 'ai_suggestions.approve', 'ai_suggestions.reject', 'ai_suggestions.apply', 'ai_suggestions.archive',
        'ai_media.view', 'ai_media.upload', 'ai_media.edit', 'ai_media.delete', 'ai_media.approve',
        'ai_seo.approve', 'ai_blog.approve', 'ai_theme.approve',
        'meta_ads.view', 'meta_ads.draft', 'meta_ads.approve', 'meta_ads.publish',
        'gift_finder.view', 'gift_finder.manage',
        'integration_clients.view', 'integration_clients.manage',
    ];

    /**
     * Preset role `marketer`: the marketing work itself. Credential issuing (API tokens, n8n clients), the API request log and
     * publishing to Meta (spends money) stay with the owner; grant them per account in Roles if needed.
     */
    public const MARKETER = [
        'marketing.view', 'marketing.analytics',
        'ai_suggestions.view', 'ai_suggestions.create', 'ai_suggestions.edit', 'ai_suggestions.approve', 'ai_suggestions.reject', 'ai_suggestions.apply', 'ai_suggestions.archive',
        'ai_media.view', 'ai_media.upload', 'ai_media.edit', 'ai_media.delete', 'ai_media.approve',
        'ai_seo.approve', 'ai_blog.approve', 'ai_theme.approve',
        'meta_ads.view', 'meta_ads.draft', 'meta_ads.approve',
        'gift_finder.view', 'gift_finder.manage',
        'integration_clients.view',
    ];
}
