@props(['title' => '', 'description' => null, 'image' => null, 'type' => 'website', 'jsonld' => null, 'noindex' => false, 'breadcrumbs' => [], 'canonicalPage' => null, 'preloadImage' => null])
@php
    $tenant = current_tenant();
    $locale = app()->getLocale();
    $seo = app(\App\Services\SeoSettingsService::class)->current();
    $isHome = request()->routeIs('storefront.home');
    $suffix = ($seo['title_suffix'][$locale] ?? '') ?: $tenant->name;
    $fullTitle = $isHome && ($seo['home_title'][$locale] ?? '') !== '' ? $seo['home_title'][$locale]
        : (($title === '' || $title === $tenant->name) ? $tenant->name : $title.' | '.$suffix);
    $rawDesc = $isHome && ($seo['home_description'][$locale] ?? '') !== '' ? $seo['home_description'][$locale]
        : ($description ?: (($title && $title !== $tenant->name ? $title.' - '.$tenant->name.'. ' : '').__('store.seo_default_desc')));
    $desc = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $rawDesc))), 158, '');
    $pageNo = (int) request()->query('page', 1);
    $canonical = url()->current().($pageNo > 1 ? '?page='.$pageNo : '');
    $filtered = collect(request()->query())->except('page')->filter(fn ($v) => $v !== null && $v !== '' && $v !== [])->isNotEmpty();
    $robots = ($noindex || ! $seo['indexable']) ? 'noindex,nofollow' : ($filtered ? 'noindex,follow' : 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1');
    $abs = fn ($u) => $u ? (preg_match('#^https?://#', $u) ? $u : url($u)) : null;
    $ogImage = $abs($image ?: ($seo['default_og_image'] ?: asset('logo-512.png')));
    $ogLocale = ['ar' => 'ar_AR', 'en' => 'en_US', 'fr' => 'fr_FR', 'de' => 'de_DE', 'es' => 'es_ES', 'tr' => 'tr_TR'];
    $routeLocale = request()->route('locale');
    $sf = app(\App\Services\Storefront\StorefrontData::class);
    $menu = $sf->menu();
    $footer = $sf->footer();
    $langs = \App\Models\Language::activeList()->filter(fn ($l) => in_array($l->code, $tenant->locales, true));
    $switchUrl = fn ($code) => $routeLocale
        ? url('/'.$code.(($rest = request()->path()) && ($pos = strpos($rest, '/')) !== false ? substr($rest, $pos) : '')).(request()->getQueryString() ? '?'.request()->getQueryString() : '')
        : url()->current().'?lang='.$code;
    // language alternates never carry query strings (filters / pagination) — one URL per language
    $altUrl = fn ($code) => url('/'.$code.(($rest = request()->path()) && ($pos = strpos($rest, '/')) !== false ? substr($rest, $pos) : ''));
    $bcItems = collect($breadcrumbs)->values()->map(fn ($b, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $b[0], 'item' => $b[1]])->all();
    $orgSameAs = array_values(array_filter($seo['social']));
    $org = array_filter(['@context' => 'https://schema.org', '@type' => $seo['org_type'] ?: 'Organization', '@id' => url('/').'#org', 'name' => $tenant->name, 'url' => url('/'.$tenant->default_locale),
        'logo' => $abs($seo['org_logo'] ?: asset('logo-512.png')), 'image' => $abs($seo['org_logo'] ?: asset('logo-512.png')), 'telephone' => $seo['org_phone'] ?: null, 'email' => $seo['org_email'] ?: null,
        'sameAs' => $orgSameAs ?: null,
        'address' => ($seo['org_street'] || $seo['org_city'] || $seo['org_country']) ? array_filter(['@type' => 'PostalAddress', 'streetAddress' => $seo['org_street'], 'addressLocality' => $seo['org_city'],
            'addressRegion' => $seo['org_region'], 'postalCode' => $seo['org_postal'], 'addressCountry' => $seo['org_country']]) : null]);
    $lds = collect($jsonld ? (array_is_list($jsonld) ? $jsonld : [$jsonld]) : [])->filter()->when($isHome, fn ($c) => $c->prepend($org))->when($bcItems, fn ($c) => $c->push(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $bcItems]))->values();
    $sfJs = ['symbol' => $tenant->currency_symbol, 'locale' => $locale, 'dir' => ($isRtl ?? false) ? 'rtl' : 'ltr', 'logged' => auth('web')->check(),
        'routes' => ['cart' => route('storefront.cart.show'), 'cartItems' => route('storefront.cart.add'), 'quote' => route('storefront.cart.quote'), 'suggest' => route('storefront.search.suggest'),
            'stockRequest' => route('storefront.stock-requests.store'), 'checkout' => route('storefront.checkout.create'), 'shipping' => route('storefront.checkout.shipping'), 'login' => route('storefront.login')],
        'i' => ['d' => __('store.d'), 'h' => __('store.h'), 'm' => __('store.m'), 's' => __('store.s'), 'free' => __('store.free'), 'cart_empty' => __('store.cart_empty'), 'remove' => __('store.remove'),
            'no_results' => __('store.no_results'), 'products' => __('store.suggest_products'), 'categories' => __('store.suggest_categories'), 'in_stock' => __('store.in_stock'),
            'out_of_stock' => __('store.out_of_stock'), 'low_stock' => __('store.low_stock', ['n' => ':n']), 'select_city_hint' => __('store.select_city_hint'), 'no_shipping' => __('store.no_shipping'),
            'eta' => __('store.eta_days', ['d' => ':d']), 'cod_na' => __('store.cod_not_for_shipping'), 'unavailable' => __('store.unavailable_combo'), 'item_unavailable' => __('store.item_unavailable'),
            'wishlist_login' => __('store.wishlist_login'), 'code_applied' => __('store.code_applied'), 'fee_note' => __('store.fee_note', ['fee' => ':fee']),
            'subtotal' => __('store.subtotal'), 'discount' => __('store.discount'), 'shipping' => __('store.shipping'), 'tax' => __('store.tax'), 'packaging' => __('store.packaging'), 'payment_fee' => __('store.payment_fee'), 'cod_fee' => __('store.cod_fee'), 'total' => __('store.total'), 'prepaid_now' => __('store.prepaid_now'), 'cod_balance' => __('store.cod_balance'),
            'request_now' => __('store.request_now'), 'variants_availability' => __('store.variants_availability'), 'unit' => __('store.unit_price'), 'qty' => __('store.qty')]];
@endphp
<x-layouts.base :title="$fullTitle" :full-title="$fullTitle" body-class="bg-slate-50" :site-name="$tenant->name">
    <x-slot:head>
        <meta name="description" content="{{ $desc }}">
        <meta name="robots" content="{{ $robots }}">
        <link rel="canonical" href="{{ $canonical }}">
        @if ($routeLocale && $robots !== 'noindex,nofollow')
            @foreach ($langs as $l)<link rel="alternate" hreflang="{{ $l->code }}" href="{{ $altUrl($l->code) }}">@endforeach
            <link rel="alternate" hreflang="x-default" href="{{ $altUrl($tenant->default_locale) }}">
        @endif
        <meta name="theme-color" content="#a8456c">
        @if (($mediaHost = parse_url(media_url('x'), PHP_URL_HOST)) && $mediaHost !== request()->getHost())<link rel="preconnect" href="{{ parse_url(media_url('x'), PHP_URL_SCHEME) }}://{{ $mediaHost }}" crossorigin>@endif
        <link rel="apple-touch-icon" href="{{ asset('logo-192.png') }}">
        @foreach (['google-site-verification' => 'google_verification', 'msvalidate.01' => 'bing_verification', 'yandex-verification' => 'yandex_verification', 'p:domain_verify' => 'pinterest_verification', 'facebook-domain-verification' => 'facebook_domain_verification'] as $metaName => $k)
            @if ($seo[$k])<meta name="{{ $metaName }}" content="{{ $seo[$k] }}">@endif
        @endforeach
        <meta property="og:site_name" content="{{ $tenant->name }}"><meta property="og:type" content="{{ $type === 'product' ? 'product' : 'website' }}">
        <meta property="og:title" content="{{ $fullTitle }}"><meta property="og:description" content="{{ $desc }}"><meta property="og:url" content="{{ $canonical }}">
        <meta property="og:locale" content="{{ $ogLocale[$locale] ?? $locale }}">
        @foreach ($langs as $l)@if ($l->code !== $locale)<meta property="og:locale:alternate" content="{{ $ogLocale[$l->code] ?? $l->code }}">@endif @endforeach
        <meta property="og:image" content="{{ $ogImage }}"><meta property="og:image:alt" content="{{ $title ?: $tenant->name }}">
        <meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="{{ $fullTitle }}"><meta name="twitter:description" content="{{ $desc }}"><meta name="twitter:image" content="{{ $ogImage }}">
        @if ($seo['twitter_handle'])<meta name="twitter:site" content="{{ '@'.ltrim($seo['twitter_handle'], '@') }}">@endif
        @if ($preloadImage)<link rel="preload" as="image" href="{{ $preloadImage }}" fetchpriority="high">@endif
        @foreach ($lds as $ld)<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>@endforeach
        <script>window.__sf = {{ Illuminate\Support\Js::from($sfJs) }};</script>
        @include('storefront._tracking', ['seo' => $seo])
    </x-slot:head>

    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-[60] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-brand-800 focus:shadow-lg">{{ __('store.skip_to_content') }}</a>
    <div class="bg-brand-800 py-1.5 text-center text-xs font-medium text-white">{{ __('store.promo') }}</div>

    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white shadow-sm">
        <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3">
            <button type="button" class="rounded-lg border border-slate-200 p-2 lg:hidden" data-drawer-open aria-label="{{ __('store.menu') }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <a href="{{ route('storefront.home') }}" class="flex shrink-0 items-center gap-2 text-xl font-extrabold text-brand-600"><img src="{{ asset('logo-sm.webp') }}" alt="{{ $tenant->name }}" width="67" height="56" fetchpriority="high" class="h-14 w-auto max-w-[240px] object-contain"></a>

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
                    <button type="button" data-dropdown-toggle="mini-cart" class="relative flex items-center gap-1 rounded-lg px-2 py-2 text-slate-600 hover:bg-slate-100">
                        <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h8.8a1 1 0 0 0 1-.8L20 8H6"/><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/></svg>
                        <span class="sr-only">{{ __('store.cart') }}</span>
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
                @if (app(\App\Services\Growth\GrowthFeatures::class)->active('gift_finder'))
                    <li><a href="{{ route('storefront.gift-finder.show') }}" class="block px-3 py-2.5 text-brand-600 hover:underline">{{ __('growth_gaps.gift_nav') }}</a></li>
                @endif
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
            @php $renderTree($menu); @endphp
            <div class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-sm">
                @auth('web')<a class="block" href="{{ route('storefront.account.index') }}">{{ __('store.my_account') }}</a>@else<a class="block" href="{{ route('storefront.login') }}">{{ __('store.login') }}</a><a class="block" href="{{ route('storefront.register') }}">{{ __('store.register') }}</a>@endauth
            </div>
        </aside>
    </div>

    <main id="main" tabindex="-1" class="mx-auto min-h-[50vh] max-w-7xl px-4 py-6 max-lg:pb-24">{{ $slot }}</main>

    <footer class="mt-10 max-lg:mb-16 border-t border-slate-200 bg-brand-50 text-slate-600">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:grid-cols-2 lg:grid-cols-4">
            <div><a href="{{ route('storefront.home') }}" class="mb-3 inline-block"><img src="{{ asset('logo-sm.webp') }}" alt="{{ $tenant->name }}" width="58" height="48" loading="lazy" class="h-12 w-auto max-w-[200px] object-contain"></a><p class="text-sm leading-6 text-slate-500">{{ __('store.seo_default_desc') }}</p></div>
            <div><h2 class="mb-3 font-semibold text-slate-900">{{ __('store.about') }}</h2>
                <ul class="space-y-2 text-sm">@foreach ($footer['pages'] as $fp)<li><a class="hover:text-slate-900" href="{{ route('storefront.pages.show', $fp['slug']) }}">{{ $fp['title'] }}</a></li>@endforeach @if ($footer['blog'] ?? false)<li><a class="hover:text-slate-900" href="{{ route('storefront.blog.index') }}">{{ __('growth.blog') }}</a></li>@endif</ul></div>
            <div><h2 class="mb-3 font-semibold text-slate-900">{{ __('store.customer_service') }}</h2>
                <ul class="space-y-2 text-sm"><li><a class="hover:text-slate-900" href="{{ route('storefront.account.index') }}">{{ __('store.my_orders') }}</a></li><li><a class="hover:text-slate-900" href="{{ route('storefront.cart.show') }}">{{ __('store.cart') }}</a></li><li><a class="hover:text-slate-900" href="{{ route('storefront.affiliate.login') }}">{{ __('store.affiliate_program') }}</a></li></ul></div>
            <div><h2 class="mb-3 font-semibold text-slate-900">{{ __('store.download_app') }}</h2>
                <div class="flex flex-col gap-2">
                    @foreach (['ios' => ['App Store', 'Download on the', 'M16.4 12.6c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.8-1.8-3.4-1.8-1.4-.1-2.8.9-3.5.9s-1.8-.8-3-.8c-1.5 0-3 .9-3.8 2.3-1.6 2.8-.4 7 1.2 9.3.8 1.1 1.7 2.4 2.9 2.3 1.2 0 1.6-.7 3-.7s1.8.7 3 .7 2-1.1 2.8-2.2c.9-1.3 1.2-2.5 1.3-2.6-.1 0-2.5-1-2.5-3.9zM14.1 5.7c.6-.8 1.1-1.8.9-2.9-.9 0-2 .6-2.7 1.4-.6.7-1.1 1.8-1 2.8 1.1.1 2.1-.5 2.8-1.3z'], 'android' => ['Google Play', 'Get it on', 'M4 2.8v18.4c0 .4.4.6.7.4l9.3-9.2L4.7 2.4c-.3-.2-.7 0-.7.4zm11.1 7.3l2.8-2.8-11.6-6.6 8.8 9.4zm0 3.8l-8.8 9.4 11.6-6.6-2.8-2.8zm3.5-2.5l-2 2 2 2 2.7-1.5c.8-.5.8-1.5 0-2L18.6 11.4z']] as $k => [$label, $kicker, $path])
                        @php $url = $footer['apps'][$k] ?? null; @endphp
                        <{{ $url ? 'a href='.e($url).' rel=noopener target=_blank' : 'div' }} dir="ltr" aria-label="{{ $label }}" @unless ($url) title="{{ __('store.coming_soon') }}" @endunless
                            class="group flex w-44 items-center gap-3 rounded-xl {{ $url ? 'bg-slate-900' : 'bg-slate-700' }} px-3.5 py-2 text-white shadow-sm ring-1 ring-black/10 transition {{ $url ? 'hover:-translate-y-0.5 hover:bg-black hover:shadow-md' : 'cursor-default' }}">
                            <svg class="size-7 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $path }}"/></svg>
                            <span class="flex flex-col leading-tight"><span class="text-[10px] uppercase tracking-wide text-white/70">{{ $url ? $kicker : __('store.coming_soon') }}</span><span class="text-base font-semibold">{{ $label }}</span></span>
                        </{{ $url ? 'a' : 'div' }}>
                    @endforeach
                </div>
                <h2 class="mb-2 mt-5 font-semibold text-slate-900">{{ __('store.we_accept') }}</h2>
                <div class="flex flex-wrap gap-2">@foreach ($footer['payments'] as $pm)<x-payment-icon :method="$pm" />@endforeach</div></div>
        </div>
        <div class="border-t border-slate-200 py-4 text-center text-xs text-slate-500">&copy; {{ now()->year }} {{ $tenant->name }}. {{ __('store.rights') }}</div>
    </footer>

    {{-- out-of-stock request modal (opened by [data-stock-request]) --}}
    <div class="fixed inset-0 z-50 hidden place-items-center bg-black/50 p-4" data-sr-modal>
        <form class="relative w-full max-w-md space-y-3 rounded-2xl bg-white p-5 shadow-2xl" data-sr-form novalidate>
            <button type="button" data-sr-close class="absolute end-3 top-3 text-2xl leading-none text-slate-400 hover:text-slate-700" aria-label="{{ __('store.close') }}">&times;</button>
            <h3 class="text-lg font-extrabold">{{ __('store.request_title') }}</h3>
            <p class="text-sm font-semibold text-brand-600" data-sr-item></p>
            <p class="text-sm text-slate-500">{{ __('store.request_intro') }}</p>
            <input type="hidden" name="product_id"><input type="hidden" name="variant_id">
            <div><label class="form-label">{{ __('store.your_name') }}</label><input type="text" name="name" maxlength="120" required class="form-control" value="{{ auth('web')->user()?->name }}"></div>
            <div><label class="form-label">{{ __('store.your_phone') }}</label>
                <div class="flex items-stretch" dir="ltr">@if ($dial = \App\Support\Phone::tenantCode())<span class="grid place-items-center rounded-s-lg border border-e-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-600">{{ $dial }}</span>@endif<input type="tel" name="phone" maxlength="30" required class="form-control {{ $dial ? '!rounded-s-none' : '' }}"></div></div>
            <div><label class="form-label">{{ __('store.your_note') }}</label><textarea name="note" rows="2" maxlength="500" class="form-control"></textarea></div>
            <button type="submit" class="btn btn-primary w-full">{{ __('store.request_send') }}</button>
        </form>
    </div>

    @include('storefront._bottom-bar')
</x-layouts.base>
