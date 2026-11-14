@props(['title' => '', 'description' => null, 'image' => null, 'type' => 'website', 'jsonld' => null, 'noindex' => false, 'breadcrumbs' => [], 'canonicalPage' => null, 'preloadImage' => null])
@php
    $locale = app()->getLocale();
    $seo = app(\App\Services\SeoSettingsService::class)->current();
    // P5: meta title / description stored by SEO suggestions for entities without own meta columns (seo_metas), and the seasonal theme
    $seoOverride = app(\App\Services\Marketing\SeoMetaService::class)->forRequest(request(), $locale);
    $title = $seoOverride['title'] ?: $title;
    $description = $seoOverride['description'] ?: $description;
    $seasonal = app(\App\Services\Marketing\ThemeService::class)->storefront(request());
    $noindex = $noindex || $seasonal['preview'] !== null;
    $isHome = request()->routeIs('storefront.home');
    $suffix = ($seo['title_suffix'][$locale] ?? '') ?: setting('name');
    $fullTitle = $isHome && ($seo['home_title'][$locale] ?? '') !== '' ? $seo['home_title'][$locale]
        : (($title === '' || $title === setting('name')) ? setting('name') : $title.' | '.$suffix);
    $rawDesc = $isHome && ($seo['home_description'][$locale] ?? '') !== '' ? $seo['home_description'][$locale]
        : ($description ?: (($title && $title !== setting('name') ? $title.' - '.setting('name').'. ' : '').__('store.seo_default_desc')));
    $desc = \Illuminate\Support\Str::limit(\App\Support\SafeHtml::text((string) $rawDesc), 158, '');
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
    $giftOn = app(\App\Services\GiftFinder\GiftFinderSettings::class)->enabled();
    $giftActive = request()->routeIs('storefront.gift-finder.*');
    $giftIcon = '<svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"/></svg>';
    $footer = $sf->footer();
    $langs = \App\Models\Language::activeList()->filter(fn ($l) => in_array($l->code, ((array) setting('locales', [])), true));
    $switchUrl = fn ($code) => $routeLocale
        ? url('/'.$code.(($rest = request()->path()) && ($pos = strpos($rest, '/')) !== false ? substr($rest, $pos) : '')).(request()->getQueryString() ? '?'.request()->getQueryString() : '')
        : url()->current().'?lang='.$code;
    // language alternates never carry query strings (filters / pagination) — one URL per language
    $altUrl = fn ($code) => url('/'.$code.(($rest = request()->path()) && ($pos = strpos($rest, '/')) !== false ? substr($rest, $pos) : ''));
    $bcItems = collect($breadcrumbs)->values()->map(fn ($b, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $b[0], 'item' => $b[1]])->all();
    $orgSameAs = array_values(array_filter($seo['social']));
    $org = array_filter(['@context' => 'https://schema.org', '@type' => $seo['org_type'] ?: 'Organization', '@id' => url('/').'#org', 'name' => setting('name'), 'url' => url('/'.setting('default_locale')),
        'logo' => $abs($seo['org_logo'] ?: asset('logo-512.png')), 'image' => $abs($seo['org_logo'] ?: asset('logo-512.png')), 'telephone' => $seo['org_phone'] ?: null, 'email' => $seo['org_email'] ?: null,
        'sameAs' => $orgSameAs ?: null,
        'address' => ($seo['org_street'] || $seo['org_city'] || $seo['org_country']) ? array_filter(['@type' => 'PostalAddress', 'streetAddress' => $seo['org_street'], 'addressLocality' => $seo['org_city'],
            'addressRegion' => $seo['org_region'], 'postalCode' => $seo['org_postal'], 'addressCountry' => $seo['org_country']]) : null]);
    $lds = collect($jsonld ? (array_is_list($jsonld) ? $jsonld : [$jsonld]) : [])->filter()->when($isHome, fn ($c) => $c->prepend($org))->when($bcItems, fn ($c) => $c->push(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $bcItems]))->values();
    $sfJs = ['symbol' => setting('currency_symbol'), 'locale' => $locale, 'dir' => ($isRtl ?? false) ? 'rtl' : 'ltr', 'logged' => auth('web')->check(),
        'routes' => ['cart' => route('storefront.cart.show'), 'cartItems' => route('storefront.cart.add'), 'quote' => route('storefront.cart.quote'), 'suggest' => route('storefront.search.suggest'),
            'stockRequest' => route('storefront.stock-requests.store'), 'checkout' => route('storefront.checkout.create'), 'shipping' => route('storefront.checkout.shipping'), 'login' => route('storefront.login')],
        'i' => ['d' => __('store.d'), 'h' => __('store.h'), 'm' => __('store.m'), 's' => __('store.s'), 'free' => __('store.free'), 'cart_empty' => __('store.cart_empty'), 'remove' => __('store.remove'),
            'no_results' => __('store.no_results'), 'products' => __('store.suggest_products'), 'categories' => __('store.suggest_categories'), 'in_stock' => __('store.in_stock'),
            'out_of_stock' => __('store.out_of_stock'), 'low_stock' => __('store.low_stock', ['n' => ':n']), 'select_city_hint' => __('store.select_city_hint'), 'no_shipping' => __('store.no_shipping'),
            'eta' => __('store.eta_days', ['d' => ':d']), 'cod_na' => __('store.cod_not_for_shipping'), 'unavailable' => __('store.unavailable_combo'), 'item_unavailable' => __('store.item_unavailable'),
            'wishlist_login' => __('store.wishlist_login'), 'code_applied' => __('store.code_applied'), 'fee_note' => __('store.fee_note', ['fee' => ':fee']),
            'subtotal' => __('store.subtotal'), 'discount' => __('store.discount'), 'shipping' => __('store.shipping'), 'tax' => __('store.tax'), 'packaging' => __('store.packaging'), 'payment_fee' => __('store.payment_fee'), 'cod_fee' => __('store.cod_fee'), 'total' => __('store.total'), 'prepaid_now' => __('store.prepaid_now'), 'cod_balance' => __('store.cod_balance'),
            'request_now' => __('store.request_now'), 'prev' => __('store.prev'), 'next' => __('store.next'), 'pause_slides' => __('store.pause_slides'), 'play_slides' => __('store.play_slides'), 'variants_availability' => __('store.variants_availability'), 'unit' => __('store.unit_price'), 'qty' => __('store.qty'), 'link_copied' => __('store.link_copied'), 'remove_filter' => __('store.remove_filter', ['name' => ':name']), 'bundle' => __('store.bundle'), 'qty_decrease' => __('store.qty_decrease'), 'qty_increase' => __('store.qty_increase'), 'copied' => __('store.copied')]];
@endphp
<x-layouts.base :title="$fullTitle" :full-title="$fullTitle" body-class="bg-slate-50" :site-name="setting('name')">
    <x-slot:head>
        <meta name="description" content="{{ $desc }}">
        <meta name="robots" content="{{ $robots }}">
        <link rel="canonical" href="{{ $canonical }}">
        @if ($routeLocale && $robots !== 'noindex,nofollow')
            @foreach ($langs as $l)<link rel="alternate" hreflang="{{ $l->code }}" href="{{ $altUrl($l->code) }}">@endforeach
            <link rel="alternate" hreflang="x-default" href="{{ $altUrl(setting('default_locale')) }}">
        @endif
        <meta name="theme-color" content="{{ $seasonal['theme_color'] ?? '#a8456c' }}">
        @if ($seasonal['css'] !== '')<style id="seasonal-theme" data-theme-preview="{{ $seasonal['preview']?->id }}">{!! $seasonal['css'] !!}</style>@endif
        @if (($mediaHost = parse_url(media_url('x'), PHP_URL_HOST)) && $mediaHost !== request()->getHost())<link rel="preconnect" href="{{ parse_url(media_url('x'), PHP_URL_SCHEME) }}://{{ $mediaHost }}" crossorigin>@endif
        {{-- fonts: Cairo (body) + Baloo Bhaijaan 2 (display), only the weights the design uses; the storefront alone loads them --}}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@600;700;800&family=Cairo:wght@400;600;700;800&display=swap">
        <link rel="apple-touch-icon" href="{{ asset('logo-192.png') }}">
        @foreach (['google-site-verification' => 'google_verification', 'msvalidate.01' => 'bing_verification', 'yandex-verification' => 'yandex_verification', 'p:domain_verify' => 'pinterest_verification', 'facebook-domain-verification' => 'facebook_domain_verification'] as $metaName => $k)
            @if ($seo[$k])<meta name="{{ $metaName }}" content="{{ $seo[$k] }}">@endif
        @endforeach
        <meta property="og:site_name" content="{{ setting('name') }}"><meta property="og:type" content="{{ $type === 'product' ? 'product' : 'website' }}">
        <meta property="og:title" content="{{ $fullTitle }}"><meta property="og:description" content="{{ $desc }}"><meta property="og:url" content="{{ $canonical }}">
        <meta property="og:locale" content="{{ $ogLocale[$locale] ?? $locale }}">
        @foreach ($langs as $l)@if ($l->code !== $locale)<meta property="og:locale:alternate" content="{{ $ogLocale[$l->code] ?? $l->code }}">@endif @endforeach
        <meta property="og:image" content="{{ $ogImage }}"><meta property="og:image:alt" content="{{ $title ?: setting('name') }}">
        <meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="{{ $fullTitle }}"><meta name="twitter:description" content="{{ $desc }}"><meta name="twitter:image" content="{{ $ogImage }}">
        @if ($seo['twitter_handle'])<meta name="twitter:site" content="{{ '@'.ltrim($seo['twitter_handle'], '@') }}">@endif
        @if ($preloadImage)<link rel="preload" as="image" href="{{ $preloadImage }}" fetchpriority="high">@endif
        @foreach ($lds as $ld)<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>@endforeach
        <script>window.__sf = {{ Illuminate\Support\Js::from($sfJs) }};</script>
        @include('storefront._tracking', ['seo' => $seo])
    </x-slot:head>
    @php
        // inline icons (24px grid, 2px round stroke, lucide style); static strings only
        $ic = [
            'menu' => 'M4 6h16M4 12h16M4 18h16', 'search' => 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5',
            'user' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0', 'heart' => 'M12 20s-7-4.4-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.6-9 9-9 9z',
            'cart' => 'M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h8.8a1 1 0 0 0 1-.8L20 8H6M9 20h.01M17 20h.01', 'globe' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18',
            'gift' => 'M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z',
            'grid' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z', 'clock' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2',
            'chev' => 'm9 6 6 6-6 6', 'down' => 'm6 9 6 6 6-6', 'close' => 'M6 6l12 12M18 6 6 18',
            'truck' => 'M3 6h11v10H3zM14 9h4l3 3v4h-7M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
            'cash' => 'M3 7h18v10H3zM12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM6 10v.01M18 14v.01', 'shield' => 'M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6zM9 12l2 2 4-4',
            'logout' => 'M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l-5-5 5-5M5 12h11',
        ];
        $svg = fn (string $name, string $class = 'size-5') => '<svg class="'.$class.' shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="'.$ic[$name].'"/></svg>';
        // chevron pointing in the reading direction (start → end)
        $chev = fn (string $class = 'size-4') => '<svg class="'.$class.' shrink-0 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>';
        $promoParts = array_values(array_filter(array_map('trim', explode('|', __('store.promo')))));
        $promoIcons = ['truck', 'cash'];
        $socialPaths = [
            'facebook' => 'M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v6h4v-6h3l1-4h-4V8z', 'instagram' => 'M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4zM12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM17.5 6.5v.01',
            'x' => 'M4 4l16 16M20 4 4 20', 'youtube' => 'M3 8a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3zM10 9l5 3-5 3z',
            'tiktok' => 'M14 3v11a3 3 0 1 1-3-3M14 3c0 3 2 5 5 5', 'linkedin' => 'M4 9h4v11H4zM6 4v.01M10 20v-6a3 3 0 0 1 6 0v6M10 9v11M16 14v6h4v-6a4 4 0 0 0-4-4',
            'pinterest' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM11 8l-3 13M9 14c3 2 7 0 7-4a4 4 0 0 0-8 0', 'snapchat' => 'M12 3a5 5 0 0 1 5 5v3l2 1-2 1c1 3 3 4 4 4-2 1-3 1-4 2-2 0-3 2-5 2s-3-2-5-2c-1-1-2-1-4-2 1 0 3-1 4-4l-2-1 2-1V8a5 5 0 0 1 5-5z',
        ];
        $socials = array_filter($seo['social'] ?? [], fn ($u) => is_string($u) && $u !== '');
        $acctLabel = auth('web')->check() ? \Illuminate\Support\Str::limit(auth('web')->user()->name, 14) : __('store.my_account');
    @endphp

    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-[60] focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:font-bold focus:text-brand-800 focus:shadow-overlay">{{ __('store.skip_to_content') }}</a>
    @if ($seasonal['preview'])
        <div class="bg-amber-400 py-1.5 text-center text-xs font-semibold text-slate-900" data-theme-preview-bar>{{ __('marketing.suggestions.themes.preview_banner') }}: #{{ $seasonal['preview']->id }} {{ $seasonal['preview']->title }} · <a class="underline" href="{{ url()->current() }}?theme_preview=off">{{ __('marketing.suggestions.themes.exit_preview') }}</a></div>
    @endif

    {{-- announcement bar (scrolls away; the header below stays) --}}
    <div class="bg-brand-950 text-white" data-announcement>
        <p class="sf-container flex min-h-8 items-center justify-center gap-x-3 gap-y-0.5 py-1.5 text-center text-xs font-bold max-sm:text-[11px]">
            @foreach ($promoParts as $i => $part)
                @if ($i > 0)<span class="size-1 rounded-full bg-sun-400" aria-hidden="true"></span>@endif
                <span class="inline-flex items-center gap-1.5 {{ $i > 0 ? 'max-sm:hidden' : '' }}">@if (isset($promoIcons[$i])){!! $svg($promoIcons[$i], 'size-4 text-sun-400') !!}@endif{{ $part }}</span>
            @endforeach
        </p>
    </div>

    <header class="sticky top-0 z-40 bg-white shadow-card" data-site-header>
        <div class="sf-container flex flex-wrap items-center gap-x-3 gap-y-2 py-2 lg:flex-nowrap lg:gap-x-5 lg:py-3">
            <button type="button" class="icon-btn icon-btn-soft lg:hidden" data-drawer-open aria-label="{{ __('store.menu') }}">{!! $svg('menu', 'size-6') !!}</button>
            <a href="{{ route('storefront.home') }}" class="flex shrink-0 items-center rounded-xl"><img src="{{ asset('logo-sm.webp') }}" alt="{{ setting('name') }}" width="96" height="80" fetchpriority="high" class="h-14 w-auto max-w-[200px] object-contain lg:h-20"></a>

            <form action="{{ route('storefront.search') }}" method="GET" class="relative order-last w-full lg:order-none lg:flex-1" data-search-form role="search">
                <label for="site-search" class="sr-only">{{ __('store.search') }}</label>
                <input id="site-search" type="search" name="q" value="{{ request('q') }}" autocomplete="off" placeholder="{{ __('store.search_placeholder') }}" data-search-input
                    class="block h-11 w-full rounded-full border border-transparent bg-slate-100 ps-5 pe-14 text-sm text-slate-950 placeholder-slate-500 transition focus:border-pop-500 focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-pop-500/20 lg:h-12">
                <button class="absolute inset-y-1 end-1 grid aspect-square place-items-center rounded-full bg-brand-600 text-white transition hover:bg-brand-700" aria-label="{{ __('store.search') }}">{!! $svg('search', 'size-5') !!}</button>
                <div class="absolute inset-x-0 top-full z-50 mt-2 hidden max-h-[70vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white py-1 shadow-overlay" data-suggest></div>
            </form>

            <nav class="ms-auto flex items-center gap-1 text-sm lg:ms-0 lg:gap-2" aria-label="{{ __('store.my_account') }}">
                @if ($giftOn)
                    <a href="{{ route('storefront.gift-finder.show') }}" data-nav-gift-finder @if ($giftActive) aria-current="page" @endif aria-label="{{ __('gift_finder.nav') }}"
                        class="inline-flex size-11 shrink-0 items-center justify-center gap-2 rounded-full font-bold transition sm:w-auto sm:px-4 {{ $giftActive ? 'bg-pop-600 text-white' : 'bg-sun-400 text-slate-950 hover:bg-sun-300' }}">
                        {!! $giftIcon !!}<span class="hidden sm:inline">{{ __('gift_finder.nav') }}</span>
                    </a>
                @endif
                <div class="relative">
                    <button type="button" data-dropdown-toggle="lang-menu" class="inline-flex min-h-11 items-center gap-1.5 rounded-full px-2.5 font-bold text-slate-950 hover:bg-slate-100" aria-label="{{ __('store.language') }}">{!! $svg('globe') !!}<span>{{ strtoupper($locale) }}</span></button>
                    <div id="lang-menu" data-dropdown class="absolute end-0 z-50 mt-2 hidden min-w-36 rounded-2xl border border-slate-200 bg-white py-2 shadow-overlay">
                        @foreach ($langs as $l)<a href="{{ $switchUrl($l->code) }}" hreflang="{{ $l->code }}" class="block px-4 py-2.5 hover:bg-slate-100 {{ $l->code === $locale ? 'font-bold text-brand-600' : 'text-slate-950' }}">{{ $l->native_name }}</a>@endforeach
                    </div>
                </div>
                <div class="relative hidden sm:block">
                    <button type="button" data-dropdown-toggle="acct-menu" class="inline-flex min-h-11 items-center gap-1.5 rounded-full px-2.5 font-bold text-slate-950 hover:bg-slate-100">
                        {!! $svg('user', 'size-6') !!}<span class="hidden md:inline">{{ $acctLabel }}</span><span class="sr-only md:hidden">{{ __('store.my_account') }}</span>
                    </button>
                    <div id="acct-menu" data-dropdown class="absolute end-0 z-50 mt-2 hidden min-w-48 rounded-2xl border border-slate-200 bg-white py-2 shadow-overlay">
                        @auth('web')
                            <a class="block px-4 py-2.5 hover:bg-slate-100" href="{{ route('storefront.account.index') }}">{{ __('store.my_orders') }}</a>
                            <a class="block px-4 py-2.5 hover:bg-slate-100" href="{{ route('storefront.wishlist.index') }}">{{ __('store.wishlist') }}</a>
                            <form method="POST" action="{{ route('storefront.logout') }}">@csrf<button class="block w-full px-4 py-2.5 text-start hover:bg-slate-100">{{ __('store.logout') }}</button></form>
                        @else
                            <a class="block px-4 py-2.5 hover:bg-slate-100" href="{{ route('storefront.login') }}">{{ __('store.login') }}</a>
                            <a class="block px-4 py-2.5 hover:bg-slate-100" href="{{ route('storefront.register') }}">{{ __('store.register') }}</a>
                        @endauth
                        <a class="mt-1 block border-t border-slate-100 px-4 py-2.5 text-violet-700 hover:bg-slate-100" href="{{ route('storefront.affiliate.'.(auth('affiliate')->check() ? 'dashboard' : 'login')) }}">{{ __('store.affiliate_program') }}</a>
                    </div>
                </div>
                <a href="{{ auth('web')->check() ? route('storefront.wishlist.index') : route('storefront.login') }}" class="icon-btn icon-btn-plain max-lg:hidden" aria-label="{{ __('store.wishlist') }}">{!! $svg('heart', 'size-6') !!}</a>
                <div class="relative">
                    <button type="button" data-dropdown-toggle="mini-cart" class="icon-btn icon-btn-plain relative">
                        {!! $svg('cart', 'size-6') !!}
                        <span class="sr-only">{{ __('store.cart') }}</span>
                        <span class="absolute -end-0.5 -top-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-pop-600 px-1 text-[11px] font-bold leading-none text-white ring-2 ring-white" data-cart-count>0</span>
                    </button>
                    <div id="mini-cart" data-dropdown class="absolute end-0 z-50 mt-2 hidden w-[340px] max-w-[calc(100vw-2rem)] rounded-3xl border border-slate-200 bg-white p-4 shadow-overlay" onclick="event.stopPropagation()">
                        <div data-mini-cart class="max-h-80 divide-y divide-slate-100 overflow-y-auto text-sm"></div>
                        <div class="mt-3 hidden border-t border-slate-100 pt-3" data-mini-cart-foot>
                            <div class="mb-3 flex justify-between font-bold"><span>{{ __('store.subtotal') }}</span><span class="font-display text-lg font-extrabold" data-mini-subtotal></span></div>
                            <div class="grid grid-cols-2 gap-2">
                                <a href="{{ route('storefront.cart.show') }}" class="btn btn-secondary w-full">{{ __('store.cart') }}</a>
                                <a href="{{ route('storefront.checkout.create') }}" class="btn btn-primary w-full">{{ __('store.checkout') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
        </div>

        {{-- category bar + mega menu (desktop) --}}
        <nav class="hidden bg-brand-600 lg:block" aria-label="{{ __('store.categories') }}">
            <div class="sf-container flex items-center gap-3 py-2">
                <button type="button" data-drawer-open class="inline-flex min-h-9 shrink-0 items-center gap-2 rounded-full bg-sun-400 px-4 text-sm font-bold text-slate-950 hover:bg-sun-300">{!! $svg('grid', 'size-4') !!}{{ __('store.all_categories') }}{!! $svg('down', 'size-4') !!}</button>
                <ul class="flex min-w-0 flex-1 items-center gap-1 overflow-x-auto overscroll-x-contain text-sm font-bold [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" data-mega data-drag-scroll>
                    @foreach ($menu as $root)
                        <li class="static shrink-0" data-mega-item>
                            <a href="{{ route('storefront.category.show', $root['slug']) }}" @if ($root['children']) aria-haspopup="true" aria-expanded="false" @endif
                                class="block whitespace-nowrap rounded-full px-3 py-2 text-white/95 hover:bg-white/15 hover:text-white">{{ $root['name'] }}</a>
                            @if ($root['children'])
                                <div class="absolute inset-x-0 top-full z-30 hidden border-t border-slate-100 bg-white shadow-overlay" data-mega-panel>
                                    <div class="sf-container grid grid-cols-5 gap-8 py-8">
                                        <div class="col-span-4 grid grid-cols-4 gap-x-8 gap-y-6">
                                            @foreach ($root['children'] as $c2)
                                                <div>
                                                    <a href="{{ route('storefront.category.show', $c2['slug']) }}" class="mb-2 block text-base font-bold text-slate-950 hover:text-brand-600">{{ $c2['name'] }}</a>
                                                    @if ($c2['children'])
                                                        <ul class="space-y-1.5 text-sm font-normal">
                                                            @foreach ($c2['children'] as $c3)
                                                                <li><a href="{{ route('storefront.category.show', $c3['slug']) }}" class="text-slate-600 hover:text-brand-600">{{ $c3['name'] }}</a></li>
                                                            @endforeach
                                                        </ul>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                        <a href="{{ route('storefront.category.show', $root['slug']) }}" class="relative flex min-h-48 flex-col justify-end overflow-hidden rounded-3xl bg-pop-600 p-5 text-white">
                                            <span class="absolute -end-8 -top-8 size-32 rounded-full bg-pop-500" aria-hidden="true"></span>
                                            <span class="absolute -start-5 top-6 size-14 rounded-full border-[6px] border-sun-400" aria-hidden="true"></span>
                                            <span class="relative font-display text-2xl font-extrabold">{{ $root['name'] }}</span>
                                            <span class="relative mt-2 inline-flex items-center gap-1 text-sm font-bold text-sun-300">{{ __('store.shop_now') }}{!! $chev() !!}</span>
                                        </a>
                                    </div>
                                </div>
                            @endif
                        </li>
                    @endforeach
                    <li class="shrink-0"><a href="{{ route('storefront.bundles.index') }}" class="block whitespace-nowrap rounded-full px-3 py-2 text-white/95 hover:bg-white/15 hover:text-white">{{ __('store.bundles') }}</a></li>
                </ul>
                <a href="{{ route('storefront.home') }}#flash-sale" class="inline-flex min-h-9 shrink-0 items-center gap-2 rounded-full bg-pop-600 px-4 text-sm font-bold text-white hover:bg-pop-700">{!! $svg('clock', 'size-4') !!}{{ __('store.flash_sale') }}</a>
            </div>
        </nav>
    </header>

    {{-- mobile drawer (also opened by "all categories" on desktop) --}}
    <div class="fixed inset-0 z-50 hidden" data-drawer role="dialog" aria-modal="true" aria-label="{{ __('store.categories') }}">
        <div class="absolute inset-0 bg-black/50" data-drawer-close></div>
        <aside class="absolute inset-y-0 start-0 flex w-80 max-w-[85vw] flex-col overflow-y-auto bg-white shadow-overlay">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                <a href="{{ route('storefront.home') }}"><img src="{{ asset('logo-sm.webp') }}" alt="{{ setting('name') }}" width="58" height="48" loading="lazy" class="h-12 w-auto object-contain"></a>
                <button type="button" data-drawer-close class="icon-btn icon-btn-outline" aria-label="{{ __('store.close') }}">{!! $svg('close') !!}</button>
            </div>
            <div class="flex-1 space-y-4 p-4">
                @if ($giftOn)
                    <a href="{{ route('storefront.gift-finder.show') }}" class="relative flex items-center gap-3 overflow-hidden rounded-3xl bg-violet-500 p-4 font-bold text-white">
                        <span class="absolute -end-6 -top-6 size-20 rounded-full bg-violet-400" aria-hidden="true"></span>
                        <span class="relative grid size-11 shrink-0 place-items-center rounded-full bg-sun-400 text-slate-950">{!! $giftIcon !!}</span>
                        <span class="relative">{{ __('gift_finder.cta') }}</span>
                    </a>
                @endif
                <div>
                    <p class="mb-2 font-display text-lg font-bold text-slate-950">{{ __('store.categories') }}</p>
                    <ul class="divide-y divide-slate-100 text-sm">
                        @foreach ($menu as $root)
                            <li>
                                <div class="flex items-center">
                                    <a class="flex min-h-11 flex-1 items-center font-bold text-slate-950 hover:text-brand-600" href="{{ route('storefront.category.show', $root['slug']) }}">{{ $root['name'] }}</a>
                                    @if ($root['children'])
                                        <button type="button" class="icon-btn size-10 text-slate-600 hover:bg-slate-100" aria-expanded="false" aria-controls="drawer-cat-{{ $root['id'] }}" data-drawer-toggle aria-label="{{ $root['name'] }}">{!! $svg('down', 'size-5 transition') !!}</button>
                                    @endif
                                </div>
                                @if ($root['children'])
                                    <ul id="drawer-cat-{{ $root['id'] }}" class="hidden space-y-0.5 pb-2 ps-3" data-drawer-sub>
                                        @foreach ($root['children'] as $c2)
                                            <li><a class="flex min-h-10 items-center font-semibold text-slate-700 hover:text-brand-600" href="{{ route('storefront.category.show', $c2['slug']) }}">{{ $c2['name'] }}</a>
                                                @if ($c2['children'])
                                                    <ul class="ps-3">@foreach ($c2['children'] as $c3)<li><a class="flex min-h-9 items-center text-slate-600 hover:text-brand-600" href="{{ route('storefront.category.show', $c3['slug']) }}">{{ $c3['name'] }}</a></li>@endforeach</ul>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                        <li><a class="flex min-h-11 items-center font-bold text-slate-950 hover:text-brand-600" href="{{ route('storefront.bundles.index') }}">{{ __('store.bundles') }}</a></li>
                    </ul>
                </div>
                <div class="space-y-1 border-t border-slate-100 pt-3 text-sm font-semibold">
                    @auth('web')
                        <a class="flex min-h-11 items-center gap-3" href="{{ route('storefront.account.index') }}">{!! $svg('user') !!}{{ __('store.my_account') }}</a>
                        <a class="flex min-h-11 items-center gap-3" href="{{ route('storefront.wishlist.index') }}">{!! $svg('heart') !!}{{ __('store.wishlist') }}</a>
                    @else
                        <a class="flex min-h-11 items-center gap-3" href="{{ route('storefront.login') }}">{!! $svg('user') !!}{{ __('store.login') }}</a>
                        <a class="flex min-h-11 items-center gap-3 ps-8" href="{{ route('storefront.register') }}">{{ __('store.register') }}</a>
                    @endauth
                    <a class="flex min-h-11 items-center gap-3 text-violet-700" href="{{ route('storefront.affiliate.'.(auth('affiliate')->check() ? 'dashboard' : 'login')) }}">{!! $svg('shield') !!}{{ __('store.affiliate_program') }}</a>
                </div>
                @if ($langs->count() > 1)
                    <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                        {!! $svg('globe', 'size-5 text-slate-600') !!}<span class="sr-only">{{ __('store.language') }}</span>
                        @foreach ($langs as $l)<a href="{{ $switchUrl($l->code) }}" hreflang="{{ $l->code }}" class="chip {{ $l->code === $locale ? 'chip-on' : '' }}" @if ($l->code === $locale) aria-current="true" @endif>{{ $l->native_name }}</a>@endforeach
                    </div>
                @endif
            </div>
        </aside>
    </div>

    <main id="main" tabindex="-1" class="sf-container min-h-[50vh] py-6 max-lg:pb-24 lg:py-8">{{ $slot }}</main>

    <footer class="mt-10 text-white">
        {{-- trust band --}}
        <div class="bg-brand-800">
            {{-- phones: 2 x 2 equal cards, icon above centred text; desktop: one row, icon beside text --}}
            <ul class="sf-container grid grid-cols-2 gap-3 py-5 lg:grid-cols-4 lg:gap-6 lg:py-7">
                @foreach ([['truck', 'trust_fast'], ['cash', 'trust_cod'], ['shield', 'trust_authentic'], ['gift', 'trust_gift']] as [$icon, $key])
                    <li class="flex min-w-0 flex-col items-center gap-2 rounded-2xl bg-white/10 px-2 py-3.5 text-center lg:flex-row lg:gap-3 lg:bg-transparent lg:p-0 lg:text-start">
                        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-sun-400 text-slate-950">{!! $svg($icon, 'size-5') !!}</span>
                        <span class="min-w-0"><span class="block text-sm font-bold">{{ __('store.'.$key) }}</span><span class="mt-0.5 block text-xs/[18px] text-white/80">{{ __('store.'.$key.'_text') }}</span></span>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="bg-brand-950">
            {{-- phones: centred brand block, the two link lists side by side, app badges in one row; desktop: 4 columns --}}
            <div class="sf-container grid grid-cols-2 gap-x-6 gap-y-8 py-8 lg:grid-cols-[1.4fr_1fr_1fr_1fr] lg:gap-8 lg:py-10">
                <div class="col-span-2 border-b border-white/10 pb-7 text-center lg:col-span-1 lg:border-0 lg:pb-0 lg:text-start">
                    <a href="{{ route('storefront.home') }}" class="mb-4 inline-grid rounded-2xl bg-white p-2"><img src="{{ asset('logo-sm.webp') }}" alt="{{ setting('name') }}" width="67" height="56" loading="lazy" class="h-14 w-auto object-contain"></a>
                    <p class="mx-auto max-w-sm text-sm/[22px] text-white/80 lg:mx-0">{{ __('store.seo_default_desc') }}</p>
                    @if ($socials)
                        <p class="sr-only">{{ __('store.follow_us') }}</p>
                        <ul class="mt-5 flex flex-wrap justify-center gap-2.5 lg:justify-start">
                            @foreach ($socials as $net => $link)
                                <li><a href="{{ $link }}" rel="noopener" target="_blank" class="grid size-11 place-items-center rounded-full bg-white/10 text-white ring-1 ring-white/25 transition hover:bg-sun-400 hover:text-slate-950 hover:ring-sun-400 focus-visible:bg-sun-400 focus-visible:text-slate-950" aria-label="{{ ucfirst($net) }}">
                                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $socialPaths[$net] ?? $ic['globe'] }}"/></svg></a></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div><h2 class="mb-3 text-base font-bold text-sun-400">{{ __('store.about') }}</h2>
                    <ul class="space-y-1 text-sm">@foreach ($footer['pages'] as $fp)<li><a class="inline-flex min-h-11 items-center text-white/85 lg:min-h-9 hover:text-white hover:underline" href="{{ route('storefront.pages.show', $fp['slug']) }}">{{ $fp['title'] }}</a></li>@endforeach @if ($footer['blog'] ?? false)<li><a class="inline-flex min-h-11 items-center text-white/85 lg:min-h-9 hover:text-white hover:underline" href="{{ route('storefront.blog.index') }}">{{ __('growth.blog') }}</a></li>@endif</ul></div>
                <div><h2 class="mb-3 text-base font-bold text-sun-400">{{ __('store.customer_service') }}</h2>
                    <ul class="space-y-1 text-sm">
                        <li><a class="inline-flex min-h-11 items-center text-white/85 lg:min-h-9 hover:text-white hover:underline" href="{{ route('storefront.account.index') }}">{{ __('store.my_orders') }}</a></li>
                        <li><a class="inline-flex min-h-11 items-center text-white/85 lg:min-h-9 hover:text-white hover:underline" href="{{ route('storefront.cart.show') }}">{{ __('store.cart') }}</a></li>
                        <li><a class="inline-flex min-h-11 items-center text-white/85 lg:min-h-9 hover:text-white hover:underline" href="{{ route('storefront.affiliate.login') }}">{{ __('store.affiliate_program') }}</a></li>
                        @if ($giftOn)<li><a class="inline-flex min-h-11 items-center text-white/85 lg:min-h-9 hover:text-white hover:underline" href="{{ route('storefront.gift-finder.show') }}">{{ __('gift_finder.nav') }}</a></li>@endif
                    </ul></div>
                <div class="col-span-2 border-t border-white/10 pt-7 text-center lg:col-span-1 lg:border-0 lg:pt-0 lg:text-start"><h2 class="mb-3 text-base font-bold text-sun-400">{{ __('store.download_app') }}</h2>
                    <div class="flex justify-center gap-2 lg:flex-col lg:justify-start">
                        @foreach (['ios' => ['App Store', 'Download on the', 'M16.4 12.6c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.8-1.8-3.4-1.8-1.4-.1-2.8.9-3.5.9s-1.8-.8-3-.8c-1.5 0-3 .9-3.8 2.3-1.6 2.8-.4 7 1.2 9.3.8 1.1 1.7 2.4 2.9 2.3 1.2 0 1.6-.7 3-.7s1.8.7 3 .7 2-1.1 2.8-2.2c.9-1.3 1.2-2.5 1.3-2.6-.1 0-2.5-1-2.5-3.9zM14.1 5.7c.6-.8 1.1-1.8.9-2.9-.9 0-2 .6-2.7 1.4-.6.7-1.1 1.8-1 2.8 1.1.1 2.1-.5 2.8-1.3z'], 'android' => ['Google Play', 'Get it on', 'M4 2.8v18.4c0 .4.4.6.7.4l9.3-9.2L4.7 2.4c-.3-.2-.7 0-.7.4zm11.1 7.3l2.8-2.8-11.6-6.6 8.8 9.4zm0 3.8l-8.8 9.4 11.6-6.6-2.8-2.8zm3.5-2.5l-2 2 2 2 2.7-1.5c.8-.5.8-1.5 0-2L18.6 11.4z']] as $k => [$label, $kicker, $path])
                            @php $url = $footer['apps'][$k] ?? null; @endphp
                            <{{ $url ? 'a href='.e($url).' rel=noopener target=_blank' : 'div' }} dir="ltr" aria-label="{{ $label }}" @unless ($url) title="{{ __('store.coming_soon') }}" @endunless
                                class="group flex min-w-0 flex-1 items-center gap-2.5 rounded-2xl border border-white/40 bg-slate-950/60 px-3 py-2 text-start text-white transition sm:max-w-48 lg:w-48 lg:flex-none lg:px-3.5 {{ $url ? 'hover:-translate-y-0.5 hover:border-white hover:bg-black' : 'cursor-default opacity-80' }}">
                                <svg class="size-7 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $path }}"/></svg>
                                <span class="flex flex-col leading-tight"><span class="text-[10px] font-bold uppercase text-white/75">{{ $url ? $kicker : __('store.coming_soon') }}</span><span class="text-base font-bold">{{ $label }}</span></span>
                            </{{ $url ? 'a' : 'div' }}>
                        @endforeach
                    </div>
                    @if ($footer['payments'])
                        <h2 class="mb-2 mt-5 text-base font-bold text-sun-400">{{ __('store.we_accept') }}</h2>
                        <div class="flex flex-wrap justify-center gap-2 lg:justify-start">@foreach ($footer['payments'] as $pm)<x-payment-icon :method="$pm" />@endforeach</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="bg-slate-950 py-4 text-center text-xs text-white/80 max-lg:pb-[calc(88px+env(safe-area-inset-bottom))]">&copy; {{ now()->year }} {{ setting('name') }}. {{ __('store.rights') }}</div>
    </footer>

    {{-- out-of-stock request modal (opened by [data-stock-request]); a bottom sheet on phones --}}
    <div class="fixed inset-0 z-50 hidden place-items-center bg-black/50 p-4 max-sm:items-end max-sm:p-0" data-sr-modal>
        <form class="relative w-full max-w-md space-y-3 rounded-3xl bg-white p-5 shadow-overlay max-sm:max-w-none max-sm:rounded-b-none max-sm:pb-[calc(1.25rem+env(safe-area-inset-bottom))]" data-sr-form novalidate role="dialog" aria-modal="true" aria-labelledby="sr-title">
            <span class="mx-auto -mt-2 mb-1 block h-1.5 w-10 rounded-full bg-slate-200 sm:hidden" aria-hidden="true"></span>
            <button type="button" data-sr-close class="icon-btn icon-btn-plain absolute end-3 top-3" aria-label="{{ __('store.close') }}">{!! $svg('close') !!}</button>
            <h3 id="sr-title" class="pe-10 font-display text-[22px]/[30px] font-bold text-slate-950">{{ __('store.request_title') }}</h3>
            <p class="text-sm font-semibold text-brand-600" data-sr-item></p>
            <p class="text-sm text-slate-600">{{ __('store.request_intro') }}</p>
            <input type="hidden" name="product_id"><input type="hidden" name="variant_id">
            <div><label class="form-label" for="sr-name">{{ __('store.your_name') }}</label><input id="sr-name" type="text" name="name" maxlength="120" required class="form-control" value="{{ auth('web')->user()?->name }}"></div>
            <div><label class="form-label" for="sr-phone">{{ __('store.your_phone') }}</label>
                <div class="flex items-stretch" dir="ltr">@if ($dial = \App\Support\Phone::shopCode())<span class="grid place-items-center rounded-s-xl border border-e-0 border-slate-300 bg-slate-100 px-3 text-sm font-semibold text-slate-600">{{ $dial }}</span>@endif<input id="sr-phone" type="tel" inputmode="tel" name="phone" maxlength="30" required class="form-control {{ $dial ? '!rounded-s-none' : '' }}"></div></div>
            <div><label class="form-label" for="sr-note">{{ __('store.your_note') }}</label><textarea id="sr-note" name="note" rows="2" maxlength="500" class="form-control"></textarea></div>
            <button type="submit" class="btn btn-primary btn-lg w-full">{{ __('store.request_send') }}</button>
        </form>
    </div>

    @include('storefront._bottom-bar')
</x-layouts.base>
