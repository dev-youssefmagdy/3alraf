<?php

namespace App\Support\Marketing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;

/**
 * Sidebar of the marketer panel (<x-layouts.marketer>), shared with the dashboard fallback redirect.
 *
 * EXTENSION POINT (P5 Suggestions Center, P6 social library / Meta, P7 MCP): add items here. An item is shown only when its route exists
 * (Route::has) AND the admin holds its permission, so placeholders for routes that do not exist yet stay hidden until the phase ships them.
 * Item = [route name, label key (lang group `marketing`), permission|null, svg path, active route pattern(s)|null].
 */
final class MarketerNav
{
    public static function groups(): array
    {
        $chart = 'M4 19V9m5 10V5m5 14v-7m5 7V8';

        return [
            ['key' => 'overview', 'icon' => 'M3 12l9-9 9 9M5 10v10h14V10', 'items' => [
                ['marketer.dashboard', 'nav.dashboard', 'marketing.view', 'M3 12l9-9 9 9M5 10v10h14V10', 'marketer.dashboard'],
                ['marketer.analytics.index', 'nav.analytics', 'marketing.analytics', $chart, 'marketer.analytics.*'],
            ]],
            // P5: Suggestions Center (MCP.md §24) — hidden until the routes exist
            ['key' => 'suggestions', 'icon' => 'M9 18h6M10 22h4M12 2a7 7 0 00-4 12.7V17h8v-2.3A7 7 0 0012 2z', 'items' => [
                ['marketer.suggestions.index', 'nav.suggestions_overview', 'ai_suggestions.view', null, 'marketer.suggestions.index'],
                ['marketer.suggestions.products', 'nav.suggestions_products', 'ai_suggestions.view', null, null],
                ['marketer.suggestions.bundles', 'nav.suggestions_bundles', 'ai_suggestions.view', null, null],
                ['marketer.suggestions.banners', 'nav.suggestions_banners', 'ai_suggestions.view', null, null],
                ['marketer.suggestions.offers', 'nav.suggestions_offers', 'ai_suggestions.view', null, null],
                ['marketer.suggestions.coupons', 'nav.suggestions_coupons', 'ai_suggestions.view', null, null],
                ['marketer.suggestions.themes', 'nav.suggestions_themes', 'ai_suggestions.view', null, null],
                ['marketer.suggestions.seo', 'nav.suggestions_seo', 'ai_suggestions.view', null, null],
                ['marketer.suggestions.blog', 'nav.suggestions_blog', 'ai_suggestions.view', null, null],
                ['marketer.suggestions.social', 'nav.suggestions_social', 'ai_suggestions.view', null, null],
                ['marketer.suggestions.log', 'nav.suggestions_log', 'ai_suggestions.view', null, null],
            ]],
            // P6: private social-media library + Meta campaign drafts — hidden until the routes exist
            ['key' => 'social', 'icon' => 'M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5', 'items' => [
                ['marketer.media.index', 'nav.media_library', 'ai_media.view', null, 'marketer.media.*'],
                ['marketer.meta-ads.index', 'nav.meta_ads', 'meta_ads.view', null, 'marketer.meta-ads.*'],
            ]],
            ['key' => 'gift_finder', 'icon' => 'M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7a3 3 0 100-4c-2 0-3 2-3 4M12 7a3 3 0 110-4c2 0 3 2 3 4', 'items' => [
                ['marketer.gift-finder.index', 'nav.gift_finder', 'gift_finder.view', null, 'marketer.gift-finder.index'],
                ['marketer.gift-finder.tags', 'nav.gift_tags', 'gift_finder.view', null, 'marketer.gift-finder.tags*'],
            ]],
            // Existing admin marketing modules: links only, gated by their own permissions (they open in the admin panel)
            ['key' => 'tools', 'icon' => 'M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.8-3.8a6 6 0 01-7.9 7.9l-6.9 6.9a2.1 2.1 0 01-3-3l6.9-6.9a6 6 0 017.9-7.9z', 'external' => true, 'items' => [
                ['admin.banners.index', 'tools.banners', 'banners.view', null, null],
                ['admin.discount-codes.index', 'tools.discount_codes', 'discount_codes.view', null, null],
                ['admin.flash-sales.index', 'tools.flash_sales', 'flash_sales.view', null, null],
                ['admin.bundles.index', 'tools.bundles', 'bundles.view', null, null],
                ['admin.blog-posts.index', 'tools.blog_posts', 'blog_posts.view', null, null],
                ['admin.static-pages.index', 'tools.static_pages', 'static_pages.view', null, null],
                ['admin.custom-pages.index', 'tools.custom_pages', 'custom_pages.view', null, null],
                ['admin.seo.edit', 'tools.seo', 'settings.view', null, null],
                ['admin.affiliates.index', 'tools.affiliates', 'affiliates.view', null, null],
                ['admin.product-lists.index', 'tools.product_lists', 'product_lists.view', null, null],
                ['admin.reviews.index', 'tools.reviews', 'reviews.view', null, null],
            ]],
            ['key' => 'access', 'icon' => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.7 5.7L11 17H9v2H7v2H3v-4l5.3-5.3A6 6 0 1121 9z', 'items' => [
                ['marketer.tokens.index', 'nav.api_tokens', 'marketing.tokens.manage', null, 'marketer.tokens.*'],
                ['marketer.api-log.index', 'nav.api_log', 'marketing.audit.view', null, 'marketer.api-log.*'],
                ['marketer.integration-clients.index', 'nav.integration_clients', 'integration_clients.view', null, 'marketer.integration-clients.*'],
            ]],
        ];
    }

    /** Groups with only the items the admin may open (and whose routes exist); empty groups removed. */
    public static function visible(?Authenticatable $user): array
    {
        $out = [];
        foreach (self::groups() as $g) {
            $g['items'] = array_values(array_filter($g['items'], fn ($i) => Route::has($i[0]) && $user && ($i[2] === null || $user->can($i[2]))));
            if ($g['items']) {
                $out[] = $g;
            }
        }

        return $out;
    }

    /** First panel page (not an admin-panel tool link) the admin may open; null when none. */
    public static function firstRoute(?Authenticatable $user): ?string
    {
        foreach (self::visible($user) as $g) {
            if (! ($g['external'] ?? false)) {
                return $g['items'][0][0];
            }
        }

        return null;
    }

    /** Show the "Admin panel" link when the admin holds any permission outside the marketer panel (or is super-admin). */
    public static function hasAdminAccess(?Authenticatable $user): bool
    {
        if (! $user || ! method_exists($user, 'getAllPermissions')) {
            return false;
        }
        if (method_exists($user, 'hasRole') && $user->hasRole('super-admin')) {
            return true;
        }

        return $user->getAllPermissions()->pluck('name')->diff(MarketingPermissions::PANEL)->isNotEmpty();
    }
}
