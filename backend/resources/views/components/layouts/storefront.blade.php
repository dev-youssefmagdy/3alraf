@props(['title' => '', 'description' => null, 'image' => null, 'type' => 'website', 'jsonld' => null, 'noindex' => false])
@php
    $tenant = current_tenant();
    $locale = app()->getLocale();
    $desc = \Illuminate\Support\Str::limit(trim(strip_tags($description ?: __('store.seo_default_desc'))), 160, '');
    $canonical = url()->current();
    $routeLocale = request()->route('locale');
    $sf = app(\App\Services\Storefront\StorefrontData::class);
    $menu = $sf->menu();
    $footer = $sf->footer();
    $langs = \App\Models\Language::activeList()->filter(fn ($l) => in_array($l->code, $tenant->locales, true));
    $switchUrl = fn ($code) => $routeLocale
        ? url('/'.$code.(($rest = request()->path()) && ($pos = strpos($rest, '/')) !== false ? substr($rest, $pos) : '')).(request()->getQueryString() ? '?'.request()->getQueryString() : '')
        : url()->current().'?lang='.$code;
    $sfJs = ['symbol' => $tenant->currency_symbol, 'locale' => $locale, 'dir' => ($isRtl ?? false) ? 'rtl' : 'ltr', 'logged' => auth('web')->check(),
        'routes' => ['cart' => route('storefront.cart.show'), 'cartItems' => route('storefront.cart.add'), 'quote' => route('storefront.cart.quote'), 'suggest' => route('storefront.search.suggest'),
            'checkout' => route('storefront.checkout.create'), 'shipping' => route('storefront.checkout.shipping'), 'login' => route('storefront.login')],
        'i' => ['d' => __('store.d'), 'h' => __('store.h'), 'm' => __('store.m'), 's' => __('store.s'), 'free' => __('store.free'), 'cart_empty' => __('store.cart_empty'), 'remove' => __('store.remove'),
            'no_results' => __('store.no_results'), 'products' => __('store.suggest_products'), 'categories' => __('store.suggest_categories'), 'in_stock' => __('store.in_stock'),
            'out_of_stock' => __('store.out_of_stock'), 'low_stock' => __('store.low_stock', ['n' => ':n']), 'select_city_hint' => __('store.select_city_hint'), 'no_shipping' => __('store.no_shipping'),
            'eta' => __('store.eta_days', ['d' => ':d']), 'cod_na' => __('store.cod_not_for_shipping'), 'unavailable' => __('store.unavailable_combo'), 'item_unavailable' => __('store.item_unavailable'),
            'wishlist_login' => __('store.wishlist_login'), 'code_applied' => __('store.code_applied'), 'fee_note' => __('store.fee_note', ['fee' => ':fee']),
            'subtotal' => __('store.subtotal'), 'discount' => __('store.discount'), 'shipping' => __('store.shipping'), 'tax' => __('store.tax'), 'payment_fee' => __('store.payment_fee'), 'total' => __('store.total'),
            'unit' => __('store.unit_price'), 'qty' => __('store.qty')]];
@endphp
<x-layouts.base :title="$title ?: $tenant->name" body-class="bg-slate-50" :site-name="$tenant->name">
    <x-slot:head>
        <meta name="description" content="{{ $desc }}">
        <link rel="canonical" href="{{ $canonical }}">
        @if ($noindex)<meta name="robots" content="noindex,nofollow">@endif
        @if ($routeLocale)@foreach ($langs as $l)<link rel="alternate" hreflang="{{ $l->code }}" href="{{ $switchUrl($l->code) }}">@endforeach @endif
        <meta property="og:site_name" content="{{ $tenant->name }}"><meta property="og:type" content="{{ $type }}">
        <meta property="og:title" content="{{ $title ?: $tenant->name }}"><meta property="og:description" content="{{ $desc }}"><meta property="og:url" content="{{ $canonical }}">
        <meta property="og:locale" content="{{ $locale }}">
        @if ($image)<meta property="og:image" content="{{ $image }}"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:image" content="{{ $image }}">@endif
        @if ($jsonld)<script type="application/ld+json">{!! json_encode($jsonld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>@endif
        <script>window.__sf = {{ Illuminate\Support\Js::from($sfJs) }};</script>
    </x-slot:head>

    <div class="bg-brand-600 py-1.5 text-center text-xs font-medium text-white">{{ __('store.promo') }}</div>

    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white shadow-sm">
        <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3">
            <button type="button" class="rounded-lg border border-slate-200 p-2 lg:hidden" data-drawer-open aria-label="{{ __('store.menu') }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <a href="{{ route('storefront.home') }}" class="shrink-0 text-xl font-extrabold text-brand-600">{{ $tenant->name }}</a>

            <form action="{{ route('storefront.search') }}" method="GET" class="relative order-last w-full lg:order-none lg:max-w-2xl lg:flex-1" data-search-form role="search">
                <input type="search" name="q" value="{{ request('q') }}" autocomplete="off" placeholder="{{ __('store.search_placeholder') }}" class="form-control !rounded-full !py-2.5 !pe-12 ps-5" data-search-input>
                <button class="absolute inset-y-0 end-0 px-4 text-slate-500 hover:text-brand-600" aria-label="{{ __('store.search') }}">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                </button>
                <div class="absolute inset-x-0 top-full z-50 mt-1 hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl" data-suggest></div>
            </form>

            <nav class="ms-auto flex items-center gap-1 text-sm lg:ms-0">
                <div class="relative">
                    <button type="button" data-dropdown-toggle="lang-menu" class="rounded-lg px-2 py-2 text-slate-600 hover:bg-slate-100">{{ strtoupper($locale) }}</button>
                    <div id="lang-menu" data-dropdown class="absolute end-0 z-50 mt-1 hidden min-w-32 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                        @foreach ($langs as $l)<a href="{{ $switchUrl($l->code) }}" hreflang="{{ $l->code }}" class="block px-4 py-2 hover:bg-slate-50 {{ $l->code === $locale ? 'font-bold text-brand-600' : '' }}">{{ $l->native_name }}</a>@endforeach
                    </div>
                </div>
                <div class="relative hidden sm:block">
                    <button type="button" data-dropdown-toggle="acct-menu" class="flex items-center gap-1 rounded-lg px-2 py-2 text-slate-600 hover:bg-slate-100">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                        <span class="hidden md:inline">{{ auth('web')->check() ? \Illuminate\Support\Str::limit(auth('web')->user()->name, 14) : __('store.my_account') }}</span>
                    </button>
                    <div id="acct-menu" data-dropdown class="absolute end-0 z-50 mt-1 hidden min-w-44 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                        @auth('web')
                            <a class="block px-4 py-2 hover:bg-slate-50" href="{{ route('storefront.account.index') }}">{{ __('store.my_orders') }}</a>
                            <a class="block px-4 py-2 hover:bg-slate-50" href="{{ route('storefront.wishlist.index') }}">{{ __('store.wishlist') }}</a>
                            <form method="POST" action="{{ route('storefront.logout') }}">@csrf<button class="block w-full px-4 py-2 text-start hover:bg-slate-50">{{ __('store.logout') }}</button></form>
                        @else
                            <a class="block px-4 py-2 hover:bg-slate-50" href="{{ route('storefront.login') }}">{{ __('store.login') }}</a>
                            <a class="block px-4 py-2 hover:bg-slate-50" href="{{ route('storefront.register') }}">{{ __('store.register') }}</a>
                        @endauth
                        <a class="block border-t border-slate-100 px-4 py-2 hover:bg-slate-50" href="{{ route('storefront.affiliate.'.(auth('affiliate')->check() ? 'dashboard' : 'login')) }}">{{ __('store.affiliate_program') }}</a>
                    </div>
                </div>
                <div class="relative">
                    <button type="button" data-dropdown-toggle="mini-cart" class="relative flex items-center gap-1 rounded-lg px-2 py-2 text-slate-600 hover:bg-slate-100" aria-label="{{ __('store.cart') }}">
                        <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h8.8a1 1 0 0 0 1-.8L20 8H6"/><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/></svg>
                        <span class="absolute -end-0.5 -top-0.5 grid min-w-5 place-items-center rounded-full bg-brand-600 px-1 text-[11px] font-bold text-white" data-cart-count>0</span>
                    </button>
                    <div id="mini-cart" data-dropdown class="absolute end-0 z-50 mt-1 hidden w-80 max-w-[90vw] rounded-xl border border-slate-200 bg-white p-3 shadow-xl" onclick="event.stopPropagation()">
                        <div data-mini-cart class="max-h-80 divide-y divide-slate-100 overflow-y-auto text-sm"></div>
                        <div class="mt-3 hidden border-t border-slate-100 pt-3" data-mini-cart-foot>
                            <div class="mb-2 flex justify-between font-bold"><span>{{ __('store.subtotal') }}</span><span data-mini-subtotal></span></div>
                            <a href="{{ route('storefront.cart.show') }}" class="btn btn-secondary w-full">{{ __('store.cart') }}</a>
                            <a href="{{ route('storefront.checkout.create') }}" class="btn btn-primary mt-2 w-full">{{ __('store.checkout') }}</a>
                        </div>
                    </div>
                </div>
            </nav>
        </div>

        {{-- category mega menu --}}
        <nav class="hidden border-t border-slate-100 bg-white lg:block" aria-label="{{ __('store.categories') }}">
            <ul class="mx-auto flex max-w-7xl items-center gap-1 px-4 text-sm font-semibold">
                @foreach ($menu as $root)
                    <li class="group static">
                        <a href="{{ route('storefront.category.show', $root['slug']) }}" class="block px-3 py-2.5 text-slate-700 hover:text-brand-600 group-hover:text-brand-600">{{ $root['name'] }}</a>
                        @if ($root['children'])
                            <div class="absolute inset-x-0 top-full hidden border-t border-slate-100 bg-white shadow-xl group-hover:block">
                                <div class="mx-auto grid max-w-7xl grid-cols-2 gap-6 px-4 py-6 md:grid-cols-4 lg:grid-cols-5">
                                    @foreach ($root['children'] as $c2)
                                        <div>
                                            <a href="{{ route('storefront.category.show', $c2['slug']) }}" class="mb-2 block font-bold text-slate-800 hover:text-brand-600">{{ $c2['name'] }}</a>
                                            <ul class="space-y-1 text-xs font-normal">
                                                @foreach ($c2['children'] as $c3)
                                                    <li><a href="{{ route('storefront.category.show', $c3['slug']) }}" class="text-slate-500 hover:text-brand-600">{{ $c3['name'] }}</a></li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    </header>

    {{-- mobile drawer --}}
    <div class="fixed inset-0 z-50 hidden" data-drawer>
        <div class="absolute inset-0 bg-black/40" data-drawer-close></div>
        <aside class="absolute inset-y-0 start-0 w-80 max-w-[85vw] overflow-y-auto bg-white p-4 shadow-xl">
            <div class="mb-3 flex items-center justify-between"><strong>{{ __('store.categories') }}</strong><button type="button" data-drawer-close class="p-1 text-2xl leading-none" aria-label="{{ __('store.close') }}">&times;</button></div>
            @php
                $renderTree = function (array $nodes) use (&$renderTree) {
                    echo '<ul class="space-y-1 ps-3 text-sm">';
                    foreach ($nodes as $n) {
                        echo '<li><a class="block py-1.5 text-slate-700 hover:text-brand-600" href="'.e(route('storefront.category.show', $n['slug'])).'">'.e($n['name']).'</a>';
                        if ($n['children']) { $renderTree($n['children']); }
                        echo '</li>';
                    }
                    echo '</ul>';
                };
            @endphp
            @php($renderTree($menu))
            <div class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-sm">
                @auth('web')<a class="block" href="{{ route('storefront.account.index') }}">{{ __('store.my_account') }}</a>@else<a class="block" href="{{ route('storefront.login') }}">{{ __('store.login') }}</a><a class="block" href="{{ route('storefront.register') }}">{{ __('store.register') }}</a>@endauth
            </div>
        </aside>
    </div>

    <main class="mx-auto min-h-[50vh] max-w-7xl px-4 py-6">{{ $slot }}</main>

    <footer class="mt-10 bg-slate-900 text-slate-300">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:grid-cols-2 lg:grid-cols-4">
            <div><h3 class="mb-3 text-lg font-bold text-white">{{ $tenant->name }}</h3><p class="text-sm leading-6 text-slate-400">{{ __('store.seo_default_desc') }}</p></div>
            <div><h4 class="mb-3 font-semibold text-white">{{ __('store.about') }}</h4>
                <ul class="space-y-2 text-sm">@foreach ($footer['pages'] as $fp)<li><a class="hover:text-white" href="{{ route('storefront.pages.show', $fp->slug) }}">{{ $fp->title }}</a></li>@endforeach</ul></div>
            <div><h4 class="mb-3 font-semibold text-white">{{ __('store.customer_service') }}</h4>
                <ul class="space-y-2 text-sm"><li><a class="hover:text-white" href="{{ route('storefront.account.index') }}">{{ __('store.my_orders') }}</a></li><li><a class="hover:text-white" href="{{ route('storefront.cart.show') }}">{{ __('store.cart') }}</a></li><li><a class="hover:text-white" href="{{ route('storefront.affiliate.login') }}">{{ __('store.affiliate_program') }}</a></li></ul></div>
            <div><h4 class="mb-3 font-semibold text-white">{{ __('store.download_app') }}</h4>
                <div class="flex gap-2"><span class="rounded-lg border border-slate-600 px-3 py-2 text-xs">App Store</span><span class="rounded-lg border border-slate-600 px-3 py-2 text-xs">Google Play</span></div>
                <h4 class="mb-2 mt-5 font-semibold text-white">{{ __('store.we_accept') }}</h4>
                <div class="flex flex-wrap gap-2">@foreach ($footer['payments'] as $pm)<span class="rounded bg-white px-2 py-1 text-xs font-semibold text-slate-700">{{ $pm->name }}</span>@endforeach</div></div>
        </div>
        <div class="border-t border-slate-800 py-4 text-center text-xs text-slate-500">&copy; {{ now()->year }} {{ $tenant->name }}. {{ __('store.rights') }}</div>
    </footer>
</x-layouts.base>
