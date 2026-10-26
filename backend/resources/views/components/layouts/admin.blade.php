{{-- Admin shell. $guard: 'central' | 'tenant' --}}
@props(['title' => '', 'guard' => 'central'])
@php
    $isCentral = $guard === 'central';
    $user = auth($guard)->user();
    $tenant = current_tenant();
    $menu = $isCentral
        ? [['central.dashboard', 'dashboard', 'M3 12l9-9 9 9M5 10v10h14V10'], ['central.tenants.index', 'tenants', 'M3 21h18M5 21V7l7-4 7 4v14', 'tenants.view'], ['central.languages.index', 'languages', 'M3 5h12M9 3v2m1 4c-1 4-4 7-7 8m3-5c1 2 4 5 8 6M12 21l4-9 4 9m-7-3h6', 'languages.view'],
            ['central.reports.index', 'reports.reports', 'M4 19V9m5 10V5m5 14v-7m5 7V8', 'reports.view'],
            ['central.admins.index', 'access.admins', 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8z', 'admins.view'],
            ['central.roles.index', 'access.roles', 'M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z', 'roles.view'],
            ['central.products.index', 'products', 'M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10L4 17V7m8 14V11', 'products.view'],
            ['central.categories.index', 'categories', 'M4 6h16M4 12h10M4 18h6', 'categories.view'],
            ['central.brands.index', 'brands', 'M7 7h.01M3 11V5a2 2 0 012-2h6l9 9-8 8-9-9z', 'brands.view'],
            ['central.audit.index', 'access.audit_log', 'M9 12h6M9 16h6M7 3h8l4 4v14H7z', 'audit.view'],
            ['central.tags.index', 'tags', 'M7 7h.01M3 11V5a2 2 0 012-2h6l9 9-8 8-9-9z', 'tags.view'],
            ['central.attributes.index', 'attributes', 'M4 6h16M4 12h16M4 18h7', 'attributes.view']]
        : [['tenant.dashboard', 'dashboard', 'M3 12l9-9 9 9M5 10v10h14V10'], ['tenant.reports.index', 'reports.reports', 'M4 19V9m5 10V5m5 14v-7m5 7V8', 'reports.view'],
            ['tenant.admins.index', 'access.admins', 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8z', 'admins.view'],
            ['tenant.audit.index', 'access.audit_log', 'M9 12h6M9 16h6M7 3h8l4 4v14H7z', 'audit.view'],
            ['tenant.roles.index', 'access.roles', 'M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z', 'roles.view'],
            ['tenant.catalog.index', 'catalog', 'M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10L4 17V7m8 14V11', 'catalog.view'],
            ['tenant.orders.index', 'orders.orders', 'M3 4h2l2 12h10l2-9H6M9 20h.01M17 20h.01', 'orders.view'],
            ['tenant.returns.index', 'orders.returns', 'M9 14l-4-4 4-4M5 10h9a5 5 0 010 10h-3', 'returns.view'],
            ['tenant.customers.index', 'orders.customers', 'M16 11a4 4 0 10-8 0 4 4 0 008 0zM4 21a8 8 0 0116 0', 'customers.view'],
            ['tenant.discount-codes.index', 'orders.discount_codes', 'M7 7h.01M3 11V5a2 2 0 012-2h6l9 9-8 8-9-9z', 'discount_codes.view'],
            ['tenant.flash-sales.index', 'orders.flash_sales', 'M13 2L4 14h7l-1 8 9-12h-7z', 'flash_sales.view'],
            ['tenant.bundles.index', 'orders.bundles', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', 'bundles.view'],
            ['tenant.packaging.index', 'orders.packaging.title', 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4', 'packaging.view'],
            ['tenant.affiliates.index', 'growth.affiliates', 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8zM21 20v-2a4 4 0 00-3-3.9', 'affiliates.view'],
            ['tenant.affiliate-tiers.index', 'growth.tiers', 'M4 20h4v-6H4zM10 20h4V8h-4zM16 20h4V4h-4z', 'affiliate_tiers.view'],
            ['tenant.affiliate-payouts.index', 'growth.payouts', 'M12 8c-2 0-3 1-3 2s1 2 3 2 3 1 3 2-1 2-3 2m0-10V6m0 12v-2', 'affiliate_payouts.view'],
            ['tenant.product-lists.index', 'growth.product_lists', 'M5 3l7 4 7-4v14l-7 4-7-4z', 'product_lists.view'],
            ['tenant.reviews.index', 'growth.reviews', 'M12 3l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.8 6.1 21l1.2-6.5L2.5 9.9 9.1 9z', 'reviews.view'],
            ['tenant.custom-pages.index', 'growth.custom_pages', 'M7 3h8l4 4v14H7zM14 3v5h5', 'custom_pages.view'],
            ['tenant.static-pages.index', 'growth.static_pages', 'M6 3h12v18H6zM9 8h6M9 12h6M9 16h4', 'static_pages.view'],
            ['tenant.blog-posts.index', 'growth.blog_posts', 'M5 4h14v16H5zM8 8h8M8 12h8M8 16h5', 'blog_posts.view'],
            ['tenant.cities.index', 'ops.cities', 'M17.7 8.3a6 6 0 10-11.4 0c0 4 5.7 11.7 5.7 11.7s5.7-7.7 5.7-11.7z', 'cities.view'],
            ['tenant.branches.index', 'ops.branches', 'M3 9l1-5h16l1 5M5 9v11h14V9M9 20v-6h6v6', 'branches.view'],
            ['tenant.suppliers.index', 'ops.suppliers', 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8z', 'suppliers.view'],
            ['tenant.shipping-companies.index', 'ops.shipping_companies', 'M3 7h11v9H3zM14 10h4l3 3v3h-7M7 19a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm10 0a1.5 1.5 0 100-3 1.5 1.5 0 000 3z', 'shipping.view'],
            ['tenant.payment-methods.index', 'ops.payment_methods', 'M3 7h18v10H3zM3 11h18', 'payment_methods.view'],
            ['tenant.notification-settings.edit', 'notifications.settings.title', 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0', 'settings.view'],
            ['tenant.cod-fees.edit', 'ops.cod_fees', 'M12 8c-2 0-3 1-3 2s1 2 3 2 3 1 3 2-1 2-3 2m0-10V6m0 12v-2', 'settings.view'],
            ['tenant.banners.index', 'ops.banners', 'M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5', 'banners.view'],
            ['tenant.expense-categories.index', 'ops.expense_categories', 'M4 6h16M4 12h16M4 18h16', 'expense_categories.view'],
            ['tenant.expenses.index', 'ops.expenses', 'M12 8c-2 0-3 1-3 2s1 2 3 2 3 1 3 2-1 2-3 2m0-10V6m0 12v-2', 'expenses.view'],
            ['tenant.stock.index', 'ops.stock', 'M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10L4 17V7m8 14V11', 'stock.view', 'tenant.stock.index'],
            ['tenant.stock.movements', 'ops.stock_movements', 'M4 6h16M4 12h16M4 18h16', 'stock.view', 'tenant.stock.movements*'],
            ['tenant.purchases.index', 'ops.purchases', 'M3 4h2l2 12h10l2-9H6', 'purchases.view'],
            ['tenant.stock-transfers.index', 'ops.stock_transfers', 'M7 7h12l-3-3m3 13H7l3 3', 'stock_transfers.view'],
            ['tenant.stock-adjustments.index', 'ops.stock_adjustments', 'M4 20h4L19 9l-4-4L4 16z', 'stock_adjustments.view']];
    $aiMenu = $isCentral
        ? [['central.ai.providers.index', 'ai.menu.providers', 'M5 12h14M12 5v14M4 4h16v16H4z', 'ai_providers.view', 'central.ai.providers.*'], ['central.ai.models.index', 'ai.menu.models', 'M12 3l8 4.5v9L12 21l-8-4.5v-9z', 'ai_models.view', 'central.ai.models.*'],
            ['central.ai.tenants.index', 'ai.menu.tenants', 'M3 21h18M5 21V7l7-4 7 4v14', 'ai_tenants.view', 'central.ai.tenants.*'], ['central.ai.usage.index', 'ai.menu.usage', 'M4 19V9m5 10V5m5 14v-7m5 7V8', 'ai_usage.view', 'central.ai.usage.*']]
        : (app(\App\Services\Ai\AiAccess::class)->enabled($tenant)
            ? array_merge(app(\App\Services\Ai\AiAccess::class)->feature('chat', $tenant) ? [['tenant.ai.chat.index', 'ai.menu.assistant', 'M8 10h8M8 14h5M4 5h16v11H9l-5 4z', 'ai_chat.use', 'tenant.ai.chat.*']] : [],
                app(\App\Services\Ai\AiAccess::class)->feature('trends', $tenant) ? [['tenant.ai.trends.index', 'ai.menu.trends', 'M3 17l6-6 4 4 8-8M15 7h6v6', 'ai_trends.view', 'tenant.ai.trends.*'], ['tenant.ai.market-trends.index', 'ai.menu.market_trends', 'M12 3a9 9 0 100 18 9 9 0 000-18zM3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18', 'ai_trends.view', 'tenant.ai.market-trends.*']] : [],
                app(\App\Services\Ai\AiAccess::class)->feature('studio', $tenant) ? [['tenant.ai.studio.index', 'ai.menu.studio', 'M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5M9 9h.01', 'ai_studio.use', 'tenant.ai.studio.*']] : [],
                app(\App\Services\Ai\AiAccess::class)->feature('editor', $tenant) ? [['tenant.ai.editor.index', 'ai.menu.editor', 'M4 20h4L19 9l-4-4L4 16zM13 7l4 4', 'ai_editor.use', 'tenant.ai.editor.*']] : [],
                app(\App\Services\Ai\AiAccess::class)->feature('generators', $tenant) ? [['tenant.ai.generators.index', 'ai.menu.generators', 'M5 4h14v6H5zM5 14h6v6H5zM15 14h4v6h-4z', 'ai_generators.use', 'tenant.ai.generators.*']] : [],
                [['tenant.ai.settings.edit', 'ai.menu.settings', 'M12 3l8 4.5v9L12 21l-8-4.5v-9zM12 8v8M8 12h8', 'ai_settings.view', 'tenant.ai.settings.*']]) : []);
@endphp
<x-layouts.base :title="$title">
    <div class="flex min-h-screen">
        <aside id="sidebar" class="fixed inset-y-0 start-0 z-40 hidden w-64 shrink-0 flex-col bg-slate-900 p-4 md:sticky md:top-0 md:h-screen md:flex">
            <a href="{{ route($isCentral ? 'central.dashboard' : 'tenant.dashboard') }}" class="mb-6 flex items-center gap-2 px-2 text-lg font-bold text-white">
                <img src="{{ asset('logo.png') }}" alt="" class="size-8 rounded-lg object-contain">
                <span>{{ $isCentral ? config('app.name') : ($tenant?->name ?? config('app.name')) }}</span>
            </a>
            @php
                $label = fn ($i) => str_contains($i[1], '.') ? __($i[1]) : __('admin.'.$i[1]);
                $isActive = fn ($i) => request()->routeIs($i[4] ?? (Str::endsWith($i[0], '.dashboard') ? $i[0] : Str::beforeLast($i[0], '.').'.*'));
                $visible = collect($menu)->filter(fn ($i) => ! isset($i[3]) || $user?->can($i[3]))->keyBy(fn ($i) => $i[0]);
                // tenant sidebar groups: [title key, icon path, [route names]]
                $groups = $isCentral ? [] : [
                    ['admin.nav.sales', 'M3 4h2l2 12h10l2-9H6M9 20h.01M17 20h.01', ['tenant.orders.index', 'tenant.returns.index', 'tenant.customers.index', 'tenant.discount-codes.index', 'tenant.flash-sales.index', 'tenant.bundles.index', 'tenant.packaging.index']],
                    ['admin.nav.catalog_inventory', 'M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10L4 17V7m8 14V11', ['tenant.catalog.index', 'tenant.stock.index', 'tenant.stock.movements', 'tenant.purchases.index', 'tenant.stock-transfers.index', 'tenant.stock-adjustments.index', 'tenant.suppliers.index']],
                    ['admin.nav.marketing', 'M12 3l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.8 6.1 21l1.2-6.5L2.5 9.9 9.1 9z', ['tenant.affiliates.index', 'tenant.affiliate-tiers.index', 'tenant.affiliate-payouts.index', 'tenant.product-lists.index', 'tenant.reviews.index', 'tenant.banners.index']],
                    ['admin.nav.content', 'M7 3h8l4 4v14H7zM14 3v5h5', ['tenant.custom-pages.index', 'tenant.static-pages.index', 'tenant.blog-posts.index']],
                    ['admin.nav.finance', 'M12 8c-2 0-3 1-3 2s1 2 3 2 3 1 3 2-1 2-3 2m0-10V6m0 12v-2', ['tenant.expenses.index', 'tenant.expense-categories.index', 'tenant.cod-fees.edit', 'tenant.payment-methods.index']],
                    ['admin.nav.logistics', 'M3 7h11v9H3zM14 10h4l3 3v3h-7M7 19a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm10 0a1.5 1.5 0 100-3 1.5 1.5 0 000 3z', ['tenant.shipping-companies.index', 'tenant.cities.index', 'tenant.branches.index']],
                    ['admin.nav.team_access', 'M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z', ['tenant.admins.index', 'tenant.roles.index', 'tenant.audit.index']],
                    ['admin.nav.insights_settings', 'M4 19V9m5 10V5m5 14v-7m5 7V8', ['tenant.reports.index', 'tenant.notification-settings.edit']],
                ];
                $grouped = collect($groups)->flatMap(fn ($g) => $g[2])->all();
                $aiVisible = collect($aiMenu)->filter(fn ($i) => $user?->can($i[3]));
                if ($aiVisible->isNotEmpty()) {
                    $groups[] = ['ai.menu.group', 'M12 3l8 4.5v9L12 21l-8-4.5v-9zM12 8v8M8 12h8', null];
                }
                $chev = '<svg class="size-4 shrink-0 transition-transform group-open:rotate-90 rtl:group-open:-rotate-90 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>';
            @endphp
            <nav class="-me-2 flex-1 space-y-1 overflow-y-auto pe-2">
                {{-- top-level items (everything for central; dashboard etc. for tenant) --}}
                @foreach ($visible as $name => $item)
                    @continue(in_array($name, $grouped, true))
                    <a href="{{ route($item[0]) }}" class="side-link {{ $isActive($item) ? 'active' : '' }}">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item[2] }}"/></svg>
                        {{ $label($item) }}
                    </a>
                @endforeach

                @foreach ($groups as $g)
                    @php
                        $items = $g[2] === null
                            ? $aiVisible->map(fn ($i) => [$i[0], $i[1], $i[2], $i[3], $i[4]])
                            : collect($g[2])->map(fn ($n) => $visible[$n] ?? null)->filter();
                        $open = $items->contains(fn ($i) => $isActive($i));
                    @endphp
                    @continue($items->isEmpty())
                    <details class="group" @if ($open) open @endif>
                        <summary class="side-link cursor-pointer select-none list-none justify-between [&::-webkit-details-marker]:hidden {{ $open ? '!text-white' : '' }}">
                            <span class="flex items-center gap-3">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $g[1] }}"/></svg>
                                {{ __($g[0]) }}
                            </span>
                            {!! $chev !!}
                        </summary>
                        <div class="ms-5 mt-1 space-y-0.5 border-s border-white/10 ps-2">
                            @foreach ($items as $item)
                                <a href="{{ route($item[0]) }}" class="side-link !py-1.5 !text-[13px] {{ $isActive($item) ? 'active' : '' }}">{{ $label($item) }}</a>
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

                @if ($isCentral && auth('central')->user()?->can('reports.view'))
                    <form method="POST" action="{{ route('central.switch-tenant') }}" class="flex items-center gap-2">
                        @csrf
                        <label class="hidden text-xs text-slate-500 sm:block">{{ __('admin.tenant_switcher') }}</label>
                        <select name="tenant_id" onchange="this.form.submit()" class="form-control !w-auto !py-1.5">
                            <option value="">{{ __('admin.all_tenants') }}</option>
                            @foreach (\App\Models\Tenant::orderBy('name')->get(['id', 'name']) as $t)
                                <option value="{{ $t->id }}" @selected(selected_tenant_id() === $t->id)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif

                <div class="ms-auto flex items-center gap-3">
                    @foreach (\App\Models\Language::activeList() as $l)
                        @continue($l->code === app()->getLocale())
                        <a href="{{ route($guard.'.locale', $l->code) }}" class="text-xs font-semibold uppercase text-slate-500 hover:text-brand-600">{{ $l->native_name }}</a>
                    @endforeach

                    {{-- notification bell: polls (60s when realtime is on, else 15s) + Echo push (resources/js/lib/orders-form.js) --}}
                    <div class="relative">
                        <button type="button" data-dropdown-toggle="notif-menu" class="relative rounded-full p-2 text-slate-500 hover:bg-slate-100" aria-label="{{ __('admin.notifications') }}">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0"/></svg>
                            <span id="notif-count" class="absolute -end-0.5 -top-0.5 hidden min-w-4 rounded-full bg-brand-600 px-1 text-center text-[10px] font-bold leading-4 text-white"></span>
                        </button>
                        <div id="notif-menu" data-dropdown class="absolute end-0 mt-2 hidden w-80 rounded-xl border border-slate-200 bg-white shadow-lg"
                            data-url="{{ route($guard.'.notifications.index') }}" data-read-url="{{ route($guard.'.notifications.read', '__ID__') }}" data-read-all-url="{{ route($guard.'.notifications.read-all') }}"
                            @if (config('broadcasting.default') === 'reverb' && config('broadcasting.connections.reverb.key'))
                                data-channel="App.Models.{{ $isCentral ? 'CentralAdmin' : 'TenantAdmin' }}.{{ $user->id }}" data-auth-url="{{ route($guard.'.broadcasting.auth') }}"
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
                        <form method="POST" action="{{ route($guard.'.logout') }}">@csrf
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
