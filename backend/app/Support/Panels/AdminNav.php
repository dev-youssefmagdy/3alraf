<?php

namespace App\Support\Panels;

use App\Services\Admin\NavBadges;
use Illuminate\Support\Str;

/**
 * Sidebar / rail / drawer / command-palette data of the admin panel (<x-layouts.admin>). Spec section 6: 4 shortcuts + 10 groups, one group open at a time.
 * menu() item = [route name, label key, svg path, permission|null, active route pattern(s)|null]; groups() = [label key, svg path, [route names]].
 * Every link keeps its permission; nothing links out of /admin.
 */
final class AdminNav
{
    /** Pinned shortcuts at the top of the sidebar. */
    public const TOP = ['admin.dashboard', 'admin.pos.index', 'admin.orders.index', 'admin.reports.index'];

    public static function menu(): array
    {
        return [['admin.dashboard', 'dashboard', 'M3 12l9-9 9 9M5 10v10h14V10'],
            ['admin.reports.index', 'reports.reports', 'M4 19V9m5 10V5m5 14v-7m5 7V8', 'reports.view'],
            ['admin.admins.index', 'access.admins', 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8z', 'admins.view'],
            ['admin.audit.index', 'access.audit_log', 'M9 12h6M9 16h6M7 3h8l4 4v14H7z', 'audit.view'],
            ['admin.roles.index', 'access.roles', 'M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z', 'roles.view'],
            ['admin.pos.index', 'pos.title', 'M3 7h18v10H3zM3 11h18M7 15h2', 'pos.use'],
            ['admin.orders.index', 'orders.orders', 'M3 4h2l2 12h10l2-9H6M9 20h.01M17 20h.01', 'orders.view'],
            ['admin.returns.index', 'orders.returns', 'M9 14l-4-4 4-4M5 10h9a5 5 0 010 10h-3', 'returns.view'],
            ['admin.customers.index', 'orders.customers', 'M16 11a4 4 0 10-8 0 4 4 0 008 0zM4 21a8 8 0 0116 0', 'customers.view'],
            ['admin.discount-codes.index', 'orders.discount_codes', 'M7 7h.01M3 11V5a2 2 0 012-2h6l9 9-8 8-9-9z', 'discount_codes.view'],
            ['admin.flash-sales.index', 'orders.flash_sales', 'M13 2L4 14h7l-1 8 9-12h-7z', 'flash_sales.view'],
            ['admin.bundles.index', 'orders.bundles', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', 'bundles.view'],
            ['admin.packaging.index', 'orders.packaging.title', 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4', 'packaging.view'],
            ['admin.affiliates.index', 'growth.affiliates', 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8zM21 20v-2a4 4 0 00-3-3.9', 'affiliates.view'],
            ['admin.affiliate-tiers.index', 'growth.tiers', 'M4 20h4v-6H4zM10 20h4V8h-4zM16 20h4V4h-4z', 'affiliate_tiers.view'],
            ['admin.affiliate-payouts.index', 'growth.payouts', 'M12 8c-2 0-3 1-3 2s1 2 3 2 3 1 3 2-1 2-3 2m0-10V6m0 12v-2', 'affiliate_payouts.view'],
            ['admin.product-lists.index', 'growth.product_lists', 'M5 3l7 4 7-4v14l-7 4-7-4z', 'product_lists.view'],
            ['admin.stock-requests.index', 'stock_requests.title', 'M12 8v4l3 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'stock_requests.view'],
            ['admin.reviews.index', 'growth.reviews', 'M12 3l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.8 6.1 21l1.2-6.5L2.5 9.9 9.1 9z', 'reviews.view'],
            ['admin.custom-pages.index', 'growth.custom_pages', 'M7 3h8l4 4v14H7zM14 3v5h5', 'custom_pages.view'],
            ['admin.static-pages.index', 'growth.static_pages', 'M6 3h12v18H6zM9 8h6M9 12h6M9 16h4', 'static_pages.view'],
            ['admin.blog-posts.index', 'growth.blog_posts', 'M5 4h14v16H5zM8 8h8M8 12h8M8 16h5', 'blog_posts.view'],
            ['admin.cities.index', 'ops.cities', 'M17.7 8.3a6 6 0 10-11.4 0c0 4 5.7 11.7 5.7 11.7s5.7-7.7 5.7-11.7z', 'cities.view'],
            ['admin.branches.index', 'ops.branches', 'M3 9l1-5h16l1 5M5 9v11h14V9M9 20v-6h6v6', 'branches.view'],
            ['admin.suppliers.index', 'ops.suppliers', 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8z', 'suppliers.view'],
            ['admin.shipping-companies.index', 'ops.shipping_companies', 'M3 7h11v9H3zM14 10h4l3 3v3h-7M7 19a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm10 0a1.5 1.5 0 100-3 1.5 1.5 0 000 3z', 'shipping.view'],
            ['admin.payment-methods.index', 'ops.payment_methods', 'M3 7h18v10H3zM3 11h18', 'payment_methods.view'],
            ['admin.notification-settings.edit', 'notifications.settings.title', 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0', 'settings.view'],
            ['admin.whatsapp.index', 'whatsapp.title', 'M3 21l1.7-5A8.5 8.5 0 1112 20.5a8.4 8.4 0 01-4-1z', 'whatsapp.view', 'admin.whatsapp.index'],
            ['admin.whatsapp.chat.index', 'whatsapp.chat.title', 'M8 10h8M8 14h5M4 5h16v11H9l-5 4z', 'whatsapp.chat.view', 'admin.whatsapp.chat.*'],
            ['admin.seo.edit', 'seo.title', 'M21 21l-4.3-4.3M11 18a7 7 0 100-14 7 7 0 000 14zM8 11l2 2 4-4', 'settings.view'],
            ['admin.cod-fees.edit', 'ops.cod_fees', 'M12 8c-2 0-3 1-3 2s1 2 3 2 3 1 3 2-1 2-3 2m0-10V6m0 12v-2', 'settings.view'],
            ['admin.banners.index', 'ops.banners', 'M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5', 'banners.view'],
            ['admin.expense-categories.index', 'ops.expense_categories', 'M4 6h16M4 12h16M4 18h16', 'expense_categories.view'],
            ['admin.expenses.index', 'ops.expenses', 'M12 8c-2 0-3 1-3 2s1 2 3 2 3 1 3 2-1 2-3 2m0-10V6m0 12v-2', 'expenses.view'],
            ['admin.erp.hub', 'erp.menu', 'M3 6h18M3 12h18M3 18h18M7 3v18M17 3v18', 'erp.access', ['admin.erp.*', '!admin.erp.anomalies*']],
            ['admin.erp.anomalies', 'erp_anomalies', 'M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z', 'erp.audit.view', 'admin.erp.anomalies*'],
            ['admin.stock.index', 'ops.stock', 'M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10L4 17V7m8 14V11', 'stock.view', 'admin.stock.index'],
            ['admin.stock.low', 'stock_requests.low_stock', 'M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z', 'stock.view', 'admin.stock.low'],
            ['admin.stock.movements', 'ops.stock_movements', 'M4 6h16M4 12h16M4 18h16', 'stock.view', 'admin.stock.movements*'],
            ['admin.purchases.index', 'ops.purchases', 'M3 4h2l2 12h10l2-9H6', 'purchases.view'],
            ['admin.stock-transfers.index', 'ops.stock_transfers', 'M7 7h12l-3-3m3 13H7l3 3', 'stock_transfers.view'],
            ['admin.stock-adjustments.index', 'ops.stock_adjustments', 'M4 20h4L19 9l-4-4L4 16z', 'stock_adjustments.view'],
            ['admin.products.index', 'products', 'M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10L4 17V7m8 14V11', 'products.view'],
            ['admin.categories.index', 'categories', 'M4 6h16M4 12h10M4 18h6', 'categories.view'],
            ['admin.brands.index', 'brands', 'M7 7h.01M3 11V5a2 2 0 012-2h6l9 9-8 8-9-9z', 'brands.view'],
            ['admin.tags.index', 'tags', 'M7 7h.01M3 11V5a2 2 0 012-2h6l9 9-8 8-9-9z', 'tags.view'],
            ['admin.attributes.index', 'attributes', 'M4 6h16M4 12h16M4 18h7', 'attributes.view'],
            ['admin.general-settings.edit', 'settings_general', 'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V9a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z', 'general_settings.view', 'admin.general-settings.*'],
            ['admin.health.index', 'health.menu', 'M3 12h4l3-8 4 16 3-8h4', 'settings.view'],
            ['admin.system-settings.edit', 'settings_system', 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2', 'general_settings.view', 'admin.system-settings.*'],
            ['admin.languages.index', 'languages', 'M3 5h12M9 3v2m1 4c-1 4-4 7-7 8m3-5c1 2 4 5 8 6M12 21l4-9 4 9M13.5 18h5', 'languages.view']];
    }

    public static function groups(): array
    {
        return [
            ['admin.nav.sales', 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8zM21 20v-2a4 4 0 00-3-3.9', ['admin.returns.index', 'admin.customers.index', 'admin.whatsapp.chat.index', 'admin.reviews.index']],
            ['admin.nav.catalog', 'M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10L4 17V7m8 14V11', ['admin.products.index', 'admin.categories.index', 'admin.brands.index', 'admin.tags.index', 'admin.attributes.index', 'admin.product-lists.index']],
            ['admin.nav.inventory', 'M3 7h18M5 7v12h14V7M9 11h6', ['admin.stock.index', 'admin.stock.low', 'admin.purchases.index', 'admin.suppliers.index', 'admin.stock-transfers.index', 'admin.stock-adjustments.index', 'admin.stock.movements', 'admin.stock-requests.index']],
            ['admin.nav.offers', 'M7 7h.01M3 11V5a2 2 0 012-2h6l9 9-8 8-9-9z', ['admin.discount-codes.index', 'admin.flash-sales.index', 'admin.bundles.index', 'admin.banners.index', 'admin.seo.edit']],
            ['admin.nav.affiliates', 'M12 3l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.8 6.1 21l1.2-6.5L2.5 9.9 9.1 9z', ['admin.affiliates.index', 'admin.affiliate-payouts.index', 'admin.affiliate-tiers.index']],
            ['admin.nav.content', 'M7 3h8l4 4v14H7zM14 3v5h5', ['admin.blog-posts.index', 'admin.static-pages.index', 'admin.custom-pages.index', 'admin.languages.index']],
            ['admin.nav.finance', 'M12 8c-2 0-3 1-3 2s1 2 3 2 3 1 3 2-1 2-3 2m0-10V6m0 12v-2', ['admin.erp.hub', 'admin.expenses.index', 'admin.expense-categories.index', 'admin.erp.anomalies', 'admin.payment-methods.index', 'admin.cod-fees.edit']],
            ['admin.nav.logistics', 'M3 7h11v9H3zM14 10h4l3 3v3h-7M7 19a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm10 0a1.5 1.5 0 100-3 1.5 1.5 0 000 3z', ['admin.shipping-companies.index', 'admin.cities.index', 'admin.branches.index', 'admin.packaging.index']],
            ['admin.nav.team_access', 'M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z', ['admin.admins.index', 'admin.roles.index', 'admin.audit.index']],
            ['admin.nav.settings', 'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V9a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z', ['admin.general-settings.edit', 'admin.notification-settings.edit', 'admin.whatsapp.index', 'admin.system-settings.edit', 'admin.health.index']],
        ];
    }

    /** Label of a menu item: a key with a dot is a full translation key, otherwise an `admin.*` key. */
    public static function label(array $i): string
    {
        return str_contains($i[1], '.') ? __($i[1]) : __('admin.'.$i[1]);
    }

    public static function isActive(array $i): bool
    {
        $p = (array) ($i[4] ?? (Str::endsWith($i[0], '.dashboard') ? $i[0] : Str::beforeLast($i[0], '.').'.*'));
        $not = collect($p)->filter(fn ($x) => str_starts_with($x, '!'))->map(fn ($x) => substr($x, 1))->all();

        return request()->routeIs(...array_diff($p, collect($not)->map(fn ($x) => '!'.$x)->all())) && ($not === [] || ! request()->routeIs(...$not));
    }

    /** Nav for <x-panel.shell>: links and groups the user may see, with badge counts. @return array<int,array<string,mixed>> */
    public static function build($user): array
    {
        $visible = collect(self::menu())->filter(fn ($i) => ! isset($i[3]) || $user?->can($i[3]))->keyBy(fn ($i) => $i[0]);
        $badges = ! $user ? [] : app(NavBadges::class)->for($user);
        $link = fn ($i) => ['route' => $i[0], 'label' => self::label($i), 'href' => route($i[0]), 'icon' => $i[2], 'active' => self::isActive($i), 'badge' => (int) ($badges[$i[0]] ?? 0)];
        $grouped = collect(self::groups())->flatMap(fn ($g) => $g[2])->merge(self::TOP)->all();

        return collect(self::TOP)->filter(fn ($n) => $visible->has($n))->map(fn ($n) => $link($visible[$n]))
            ->merge($visible->reject(fn ($i, $n) => in_array($n, $grouped, true))->map($link)->values())
            ->merge(collect(self::groups())->map(fn ($g) => ['group' => true, 'label' => __($g[0]), 'icon' => $g[1],
                'items' => collect($g[2])->filter(fn ($n) => $visible->has($n))->map(fn ($n) => $link($visible[$n]))->values()->all()])
                ->filter(fn ($g) => $g['items'] !== []))
            ->values()->all();
    }
}
