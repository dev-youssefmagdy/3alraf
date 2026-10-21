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
            ['central.attributes.index', 'attributes', 'M4 6h16M4 12h16M4 18h7', 'attributes.view']]
        : [['tenant.dashboard', 'dashboard', 'M3 12l9-9 9 9M5 10v10h14V10'], ['tenant.reports.index', 'reports.reports', 'M4 19V9m5 10V5m5 14v-7m5 7V8', 'reports.view'],
            ['tenant.admins.index', 'access.admins', 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8z', 'admins.view'],
            ['tenant.roles.index', 'access.roles', 'M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z', 'roles.view'],
            ['tenant.catalog.index', 'catalog', 'M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10L4 17V7m8 14V11', 'catalog.view'],
            ['tenant.orders.index', 'orders.orders', 'M3 4h2l2 12h10l2-9H6M9 20h.01M17 20h.01', 'orders.view'],
            ['tenant.returns.index', 'orders.returns', 'M9 14l-4-4 4-4M5 10h9a5 5 0 010 10h-3', 'returns.view'],
            ['tenant.customers.index', 'orders.customers', 'M16 11a4 4 0 10-8 0 4 4 0 008 0zM4 21a8 8 0 0116 0', 'customers.view'],
            ['tenant.discount-codes.index', 'orders.discount_codes', 'M7 7h.01M3 11V5a2 2 0 012-2h6l9 9-8 8-9-9z', 'discount_codes.view'],
            ['tenant.flash-sales.index', 'orders.flash_sales', 'M13 2L4 14h7l-1 8 9-12h-7z', 'flash_sales.view'],
            ['tenant.affiliates.index', 'growth.affiliates', 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8zM21 20v-2a4 4 0 00-3-3.9', 'affiliates.view'],
            ['tenant.affiliate-tiers.index', 'growth.tiers', 'M4 20h4v-6H4zM10 20h4V8h-4zM16 20h4V4h-4z', 'affiliate_tiers.view'],
            ['tenant.affiliate-payouts.index', 'growth.payouts', 'M12 8c-2 0-3 1-3 2s1 2 3 2 3 1 3 2-1 2-3 2m0-10V6m0 12v-2', 'affiliate_payouts.view'],
            ['tenant.product-lists.index', 'growth.product_lists', 'M5 3l7 4 7-4v14l-7 4-7-4z', 'product_lists.view'],
            ['tenant.reviews.index', 'growth.reviews', 'M12 3l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.8 6.1 21l1.2-6.5L2.5 9.9 9.1 9z', 'reviews.view'],
            ['tenant.custom-pages.index', 'growth.custom_pages', 'M7 3h8l4 4v14H7zM14 3v5h5', 'custom_pages.view'],
            ['tenant.static-pages.index', 'growth.static_pages', 'M6 3h12v18H6zM9 8h6M9 12h6M9 16h4', 'static_pages.view'],
            ['tenant.cities.index', 'ops.cities', 'M17.7 8.3a6 6 0 10-11.4 0c0 4 5.7 11.7 5.7 11.7s5.7-7.7 5.7-11.7z', 'cities.view'],
            ['tenant.branches.index', 'ops.branches', 'M3 9l1-5h16l1 5M5 9v11h14V9M9 20v-6h6v6', 'branches.view'],
            ['tenant.suppliers.index', 'ops.suppliers', 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8z', 'suppliers.view'],
            ['tenant.shipping-companies.index', 'ops.shipping_companies', 'M3 7h11v9H3zM14 10h4l3 3v3h-7M7 19a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm10 0a1.5 1.5 0 100-3 1.5 1.5 0 000 3z', 'shipping.view'],
            ['tenant.payment-methods.index', 'ops.payment_methods', 'M3 7h18v10H3zM3 11h18', 'payment_methods.view'],
            ['tenant.banners.index', 'ops.banners', 'M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5', 'banners.view'],
            ['tenant.expense-categories.index', 'ops.expense_categories', 'M4 6h16M4 12h16M4 18h16', 'expense_categories.view'],
            ['tenant.expenses.index', 'ops.expenses', 'M12 8c-2 0-3 1-3 2s1 2 3 2 3 1 3 2-1 2-3 2m0-10V6m0 12v-2', 'expenses.view'],
            ['tenant.stock.index', 'ops.stock', 'M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10L4 17V7m8 14V11', 'stock.view', 'tenant.stock.index'],
            ['tenant.stock.movements', 'ops.stock_movements', 'M4 6h16M4 12h16M4 18h16', 'stock.view', 'tenant.stock.movements*'],
            ['tenant.purchases.index', 'ops.purchases', 'M3 4h2l2 12h10l2-9H6', 'purchases.view'],
            ['tenant.stock-transfers.index', 'ops.stock_transfers', 'M7 7h12l-3-3m3 13H7l3 3', 'stock_transfers.view'],
            ['tenant.stock-adjustments.index', 'ops.stock_adjustments', 'M4 20h4L19 9l-4-4L4 16z', 'stock_adjustments.view']];
@endphp
<x-layouts.base :title="$title">
    <div class="flex min-h-screen">
        <aside id="sidebar" class="fixed inset-y-0 start-0 z-40 hidden w-64 shrink-0 flex-col bg-slate-900 p-4 md:static md:flex">
            <a href="{{ route($isCentral ? 'central.dashboard' : 'tenant.dashboard') }}" class="mb-6 flex items-center gap-2 px-2 text-lg font-bold text-white">
                <span class="grid size-8 place-items-center rounded-lg bg-brand-600">3</span>
                <span>{{ $isCentral ? config('app.name') : ($tenant?->name ?? config('app.name')) }}</span>
            </a>
            <nav class="space-y-1">
                @foreach ($menu as $item)
                    @continue(isset($item[3]) && ! $user?->can($item[3]))
                    <a href="{{ route($item[0]) }}" class="side-link {{ request()->routeIs($item[4] ?? (Str::endsWith($item[0], '.dashboard') ? $item[0] : Str::beforeLast($item[0], '.').'.*')) ? 'active' : '' }}">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item[2] }}"/></svg>
                        {{ str_contains($item[1], '.') ? __($item[1]) : __('admin.'.$item[1]) }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3">
                <button type="button" data-sidebar-toggle class="md:hidden" aria-label="Menu">
                    <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                @if ($isCentral)
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

                    {{-- notification bell: tenant admins poll every 15s (resources/js/lib/orders-form.js) --}}
                    <div class="relative">
                        <button type="button" data-dropdown-toggle="notif-menu" class="relative rounded-full p-2 text-slate-500 hover:bg-slate-100" aria-label="{{ __('admin.notifications') }}">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0"/></svg>
                            <span id="notif-count" class="absolute -end-0.5 -top-0.5 hidden min-w-4 rounded-full bg-brand-600 px-1 text-center text-[10px] font-bold leading-4 text-white"></span>
                        </button>
                        <div id="notif-menu" data-dropdown class="absolute end-0 mt-2 hidden w-80 rounded-xl border border-slate-200 bg-white shadow-lg"
                            @unless ($isCentral) data-url="{{ route('tenant.notifications.index') }}" data-read-url="{{ route('tenant.notifications.read', '__ID__') }}" data-read-all-url="{{ route('tenant.notifications.read-all') }}" @endunless
                            data-empty="{{ __('admin.no_notifications') }}">
                            <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2 text-sm font-semibold">{{ __('admin.notifications') }}
                                @unless ($isCentral)<a id="notif-read-all" href="#" class="text-xs font-normal text-brand-600">{{ __('orders.mark_all_read') }}</a>@endunless
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
