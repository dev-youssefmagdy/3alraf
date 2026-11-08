{{-- Admin shell (guard `admin`). --}}
@props(['title' => ''])
@php
    $user = auth('admin')->user();
    $menu = [['admin.dashboard', 'dashboard', 'M3 12l9-9 9 9M5 10v10h14V10'],
            // marketer panel (/marketer, P4): Gift Finder, integration clients, API tokens, marketing dashboard live there
            ['marketer.dashboard', 'marketing.panel_link', 'M11 5.9v12.2a1.8 1.8 0 01-3.4.7L5.2 13H4a2 2 0 01-2-2V9a2 2 0 012-2h1.2l2.4-5.8A1.8 1.8 0 0111 2v3.9zM15 8a4 4 0 010 8m3-11a8 8 0 010 14', 'marketing.panel', 'marketer.*'],
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
            ['admin.languages.index', 'languages', 'M3 5h12M9 3v2m1 4c-1 4-4 7-7 8m3-5c1 2 4 5 8 6M12 21l4-9 4 9M13.5 18h5', 'languages.view']];
@endphp
<x-layouts.base :title="$title">
    <div class="flex min-h-screen">
        <aside id="sidebar" class="fixed inset-y-0 start-0 z-40 hidden w-64 shrink-0 flex-col bg-slate-900 p-4 md:sticky md:top-0 md:h-screen md:flex">
            <a href="{{ route('admin.dashboard') }}" class="mb-6 flex items-center gap-2 px-2 text-lg font-bold text-white">
                <img src="{{ asset('logo-sm.webp') }}" alt="{{ setting('name') ?: config('app.name') }}" class="h-10 w-auto max-w-full object-contain">
            </a>
            @php
                $label = fn ($i) => str_contains($i[1], '.') ? __($i[1]) : __('admin.'.$i[1]);
                $isActive = function ($i) {
                    $p = (array) ($i[4] ?? (Str::endsWith($i[0], '.dashboard') ? $i[0] : Str::beforeLast($i[0], '.').'.*'));
                    $not = collect($p)->filter(fn ($x) => str_starts_with($x, '!'))->map(fn ($x) => substr($x, 1))->all();

                    return request()->routeIs(...array_diff($p, collect($not)->map(fn ($x) => '!'.$x)->all())) && ($not === [] || ! request()->routeIs(...$not));
                };
                $visible = collect($menu)->filter(fn ($i) => ! isset($i[3]) || $user?->can($i[3]))->keyBy(fn ($i) => $i[0]);
                // sidebar groups: [title key, icon path, [route names]]
                $groups = [
                    ['admin.nav.sales', 'M3 4h2l2 12h10l2-9H6M9 20h.01M17 20h.01', ['admin.pos.index', 'admin.orders.index', 'admin.returns.index', 'admin.customers.index', 'admin.whatsapp.chat.index', 'admin.discount-codes.index', 'admin.flash-sales.index', 'admin.bundles.index', 'admin.packaging.index']],
                    ['admin.nav.catalog_inventory', 'M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10L4 17V7m8 14V11', ['admin.products.index', 'admin.categories.index', 'admin.brands.index', 'admin.tags.index', 'admin.attributes.index', 'admin.stock-requests.index', 'admin.stock.index', 'admin.stock.low', 'admin.stock.movements', 'admin.purchases.index', 'admin.stock-transfers.index', 'admin.stock-adjustments.index', 'admin.suppliers.index']],
                    ['admin.nav.marketing', 'M12 3l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.8 6.1 21l1.2-6.5L2.5 9.9 9.1 9z', ['admin.affiliates.index', 'admin.affiliate-tiers.index', 'admin.affiliate-payouts.index', 'admin.product-lists.index', 'admin.reviews.index', 'admin.banners.index']],
                    ['admin.nav.content', 'M7 3h8l4 4v14H7zM14 3v5h5', ['admin.custom-pages.index', 'admin.static-pages.index', 'admin.blog-posts.index']],
                    ['admin.nav.finance', 'M12 8c-2 0-3 1-3 2s1 2 3 2 3 1 3 2-1 2-3 2m0-10V6m0 12v-2', ['admin.erp.hub', 'admin.erp.anomalies', 'admin.expenses.index', 'admin.expense-categories.index', 'admin.cod-fees.edit', 'admin.payment-methods.index']],
                    ['admin.nav.logistics', 'M3 7h11v9H3zM14 10h4l3 3v3h-7M7 19a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm10 0a1.5 1.5 0 100-3 1.5 1.5 0 000 3z', ['admin.shipping-companies.index', 'admin.cities.index', 'admin.branches.index']],
                    ['admin.nav.team_access', 'M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z', ['admin.admins.index', 'admin.roles.index', 'admin.audit.index']],
                    ['admin.nav.insights_settings', 'M4 19V9m5 10V5m5 14v-7m5 7V8', ['admin.reports.index', 'admin.general-settings.edit', 'admin.languages.index', 'admin.notification-settings.edit', 'admin.whatsapp.index', 'admin.seo.edit']],
                ];
                $badges = ! $user ? [] : app(\App\Services\Admin\NavBadges::class)->for($user);
                $badge = fn ($n) => $n > 0 ? '<span class="ms-auto min-w-5 rounded-full bg-red-500 px-1.5 text-center text-[11px] font-bold leading-5 text-white">'.($n > 99 ? '99+' : $n).'</span>' : '';
                $grouped = collect($groups)->flatMap(fn ($g) => $g[2])->all();
                $chev = '<svg class="size-4 shrink-0 transition-transform group-open:rotate-90 rtl:group-open:-rotate-90 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>';
            @endphp
            <nav class="-me-2 flex-1 space-y-1 overflow-y-auto pe-2">
                {{-- top-level items (dashboard; anything not in a group) --}}
                @foreach ($visible as $name => $item)
                    @continue(in_array($name, $grouped, true))
                    <a href="{{ route($item[0]) }}" class="side-link {{ $isActive($item) ? 'active' : '' }}">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item[2] }}"/></svg>
                        {{ $label($item) }}
                        {!! $badge($badges[$item[0]] ?? 0) !!}
                    </a>
                @endforeach

                @foreach ($groups as $g)
                    @php
                        $items = collect($g[2])->map(fn ($n) => $visible[$n] ?? null)->filter();
                        $open = $items->contains(fn ($i) => $isActive($i));
                    @endphp
                    @continue($items->isEmpty())
                    <details class="group" @if ($open) open @endif>
                        <summary class="side-link cursor-pointer select-none list-none justify-between [&::-webkit-details-marker]:hidden {{ $open ? '!text-white' : '' }}">
                            <span class="flex items-center gap-3">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $g[1] }}"/></svg>
                                {{ __($g[0]) }}
                            </span>
                            <span class="flex items-center gap-2">{!! $badge($items->sum(fn ($i) => $badges[$i[0]] ?? 0)) !!}{!! $chev !!}</span>
                        </summary>
                        <div class="ms-5 mt-1 space-y-0.5 border-s border-white/10 ps-2">
                            @foreach ($items as $item)
                                <a href="{{ route($item[0]) }}" class="side-link !py-1.5 !text-[13px] {{ $isActive($item) ? 'active' : '' }}">{{ $label($item) }} {!! $badge($badges[$item[0]] ?? 0) !!}</a>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3">
                <button type="button" data-sidebar-toggle class="md:hidden" aria-label="Menu">
                    <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>


                <div class="ms-auto flex items-center gap-3">
                    @foreach (\App\Models\Language::activeList() as $l)
                        @continue($l->code === app()->getLocale())
                        <a href="{{ route('admin.locale', $l->code) }}" class="text-xs font-semibold uppercase text-slate-500 hover:text-brand-600">{{ $l->native_name }}</a>
                    @endforeach

                    {{-- notification bell: polls (60s when realtime is on, else 15s) + Echo push (resources/js/lib/orders-form.js) --}}
                    <div class="relative">
                        <button type="button" data-dropdown-toggle="notif-menu" class="relative rounded-full p-2 text-slate-500 hover:bg-slate-100" aria-label="{{ __('admin.notifications') }}">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0"/></svg>
                            <span id="notif-count" class="absolute -end-0.5 -top-0.5 hidden min-w-4 rounded-full bg-brand-600 px-1 text-center text-[10px] font-bold leading-4 text-white"></span>
                        </button>
                        <div id="notif-menu" data-dropdown class="absolute end-0 mt-2 hidden w-80 rounded-xl border border-slate-200 bg-white shadow-lg"
                            data-url="{{ route('admin.notifications.index') }}" data-read-url="{{ route('admin.notifications.read', '__ID__') }}" data-read-all-url="{{ route('admin.notifications.read-all') }}"
                            @if (config('broadcasting.default') === 'reverb' && config('broadcasting.connections.reverb.key'))
                                data-channel="App.Models.Admin.{{ $user->id }}" data-auth-url="{{ route('admin.broadcasting.auth') }}"
                                data-ws-key="{{ config('broadcasting.connections.reverb.key') }}" data-ws-host="{{ config('broadcasting.connections.reverb.options.host') }}"
                                data-ws-port="{{ config('broadcasting.connections.reverb.options.port') }}" data-ws-scheme="{{ config('broadcasting.connections.reverb.options.scheme') }}"
                            @endif
                            data-empty="{{ __('admin.no_notifications') }}">
                            <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2 text-sm font-semibold">{{ __('admin.notifications') }}
                                <a id="notif-read-all" href="#" class="text-xs font-normal text-brand-600">{{ __('orders.mark_all_read') }}</a>
                            </div>
                            <div id="notif-list" class="max-h-80 overflow-y-auto"><p class="p-3 text-sm text-slate-500">{{ __('admin.no_notifications') }}</p></div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 text-sm">
                        <span class="hidden text-slate-600 sm:inline">{{ $user?->name }}</span>
                        <form method="POST" action="{{ route('admin.logout') }}">@csrf
                            <button class="btn-sm btn-secondary">{{ __('admin.logout') }}</button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 md:p-6">
                @if ($title)<h1 class="mb-5 text-xl font-bold text-slate-800">{{ $title }}</h1>@endif
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
