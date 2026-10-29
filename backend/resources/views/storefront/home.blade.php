<x-layouts.storefront :title="$tenant->name" :description="__('store.seo_default_desc')" :preload-image="$hero->first()?->imageUrl()" :jsonld="['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $tenant->name, 'url' => url('/'.app()->getLocale()),
    'potentialAction' => ['@type' => 'SearchAction', 'target' => route('storefront.search').'?q={q}', 'query-input' => 'required name=q']]">
    <h1 class="sr-only">{{ (app(\App\Services\SeoSettingsService::class)->current()['home_title'][app()->getLocale()] ?? '') ?: $tenant->name }}</h1>
    {{-- hero slider --}}
    @if ($hero->isNotEmpty())
        <section class="relative cursor-grab select-none overflow-hidden rounded-2xl bg-slate-200 active:cursor-grabbing" data-slider aria-roledescription="carousel" aria-label="{{ __('store.featured_offers') }}">
            <div class="flex will-change-transform" data-slides>
                @foreach ($hero as $b)
                    <a href="{{ $b->safeLink() ?: '#' }}" class="block w-full shrink-0" data-slide>
                        @if ($b->imageUrl())<img src="{{ $b->imageUrl() }}" alt="{{ $b->title }}" width="1600" height="600" class="aspect-[16/6] w-full object-cover" @if ($loop->first) fetchpriority="high" decoding="async" @else loading="lazy" @endif>@endif
                    </a>
                @endforeach
            </div>
            @if ($hero->count() > 1)
                <button type="button" data-slide-prev class="absolute start-3 top-1/2 hidden -translate-y-1/2 rounded-full bg-white/80 px-3 py-1 text-xl shadow md:block" aria-label="{{ __('store.prev') }}">&#8249;</button>
                <button type="button" data-slide-next class="absolute end-3 top-1/2 hidden -translate-y-1/2 rounded-full bg-white/80 px-3 py-1 text-xl shadow md:block" aria-label="{{ __('store.next') }}">&#8250;</button>
                <div class="absolute inset-x-0 bottom-3 flex justify-center gap-1.5" data-dots aria-hidden="true">@foreach ($hero as $b)<span class="size-2 cursor-pointer rounded-full bg-white/60 transition-all"></span>@endforeach</div>
            @endif
        </section>
    @endif

    {{-- category icon row --}}
    @if (count($categories))
        <section class="mt-6">
            <div data-scroll-row class="flex gap-4 overflow-x-auto pb-2">
                @foreach ($categories as $c)
                    <a href="{{ route('storefront.category.show', $c['slug']) }}" class="group w-24 shrink-0 text-center">
                        <span class="mx-auto grid size-20 place-items-center overflow-hidden rounded-full bg-white ring-1 ring-slate-200 transition group-hover:ring-brand-500">
                            @if ($c['image'])<img src="{{ $c['image'] }}" alt="" class="size-full object-cover" loading="lazy">@else<span class="text-2xl font-bold text-brand-600">{{ mb_substr($c['name'], 0, 1) }}</span>@endif
                        </span>
                        <span class="mt-2 block text-xs font-semibold text-slate-700 group-hover:text-brand-600">{{ $c['name'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- promo banners --}}
    @if ($strips->isNotEmpty() || $tiles->isNotEmpty())
        <section class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-{{ min(4, max(2, $strips->count() + $tiles->count())) }}">
            @foreach ($strips->concat($tiles) as $b)
                <a href="{{ $b->safeLink() ?: '#' }}" class="block overflow-hidden rounded-xl bg-slate-100">@if ($b->imageUrl())<img src="{{ $b->imageUrl() }}" alt="{{ $b->title }}" width="800" height="350" class="aspect-[16/7] w-full object-cover transition hover:scale-105" @if ($loop->index < 2) fetchpriority="high" @else loading="lazy" @endif decoding="async">@endif</a>
            @endforeach
        </section>
    @endif

    {{-- flash sale --}}
    @if ($flash)
        <x-rail :title="$flash['sale']->name" :items="$flash['items']" :countdown="$flash['sale']->ends_at->toIso8601String()" :accent="true" />
    @endif

    @if (app(\App\Services\Growth\GrowthFeatures::class)->active('gift_finder'))
        <a href="{{ route('storefront.gift-finder.show') }}" class="mt-6 block rounded-2xl bg-brand-50 p-4 text-center text-sm font-bold text-brand-700">{{ __('growth_gaps.gift_cta') }}</a>
    @endif

    @if (! empty($bundles))
        <section class="mt-8">
            <div class="mb-3 flex items-center justify-between"><h2 class="text-lg font-extrabold">{{ __('store.bundles') }}</h2><a href="{{ route('storefront.bundles.index') }}" class="text-sm font-semibold text-brand-600">{{ __('store.view_all') }}</a></div>
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">@foreach ($bundles as $b)@include('storefront._bundle-card', ['b' => $b])@endforeach</div>
        </section>
    @endif

    <x-rail :title="__('store.recommended')" :items="$rails['recommended']" />
    <x-rail :title="__('store.most_sold')" :items="$rails['most_sold']" />
    <x-rail :title="__('store.most_reviewed')" :items="$rails['most_reviewed']" />

    @foreach ($categoryRails as $r)
        <x-rail :title="$r['category']->name" :items="$r['items']" :href="route('storefront.category.show', $r['category']->slug)" />
    @endforeach

    {{-- brand strip --}}
    @if ($brands->isNotEmpty())
        <section class="mt-8 rounded-2xl bg-white p-4 ring-1 ring-slate-100 sm:p-5">
            <div class="mb-4 flex items-center justify-between gap-2">
                <h2 class="text-lg font-extrabold text-slate-900">{{ __('store.brands') }}</h2>
                <a href="{{ route('storefront.brands.index') }}" class="text-sm font-semibold text-brand-600 hover:underline">{{ __('store.view_all') }}</a>
            </div>
            <div data-scroll-row class="-mx-1 flex snap-x gap-3 overflow-x-auto px-1 pb-2 sm:gap-4">
                @foreach ($brands as $br)
                    <a href="{{ route('storefront.brand.show', $br->slug) }}" class="group w-36 shrink-0 snap-start overflow-hidden rounded-xl bg-white ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md hover:ring-brand-500 sm:w-44">
                        <span class="grid h-24 place-items-center bg-slate-50 p-4 sm:h-28">
                            @if ($br->logo_url)<img src="{{ $br->logo_url }}" alt="{{ $br->name }}" class="max-h-full max-w-full object-contain transition duration-300 group-hover:scale-105" loading="lazy">@else<span class="grid size-14 place-items-center rounded-full bg-brand-50 text-2xl font-extrabold text-brand-600">{{ mb_strtoupper(mb_substr($br->name, 0, 1)) }}</span>@endif
                        </span>
                        <span class="block border-t border-slate-100 px-2 py-2.5 text-center">
                            <span class="block truncate text-sm font-bold text-slate-800 group-hover:text-brand-600">{{ $br->name }}</span>
                            <span class="block text-xs text-slate-500">{{ $br->sellable_count }} {{ __('store.brand_products') }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- popup banner --}}
    @if ($popup && $popup->imageUrl())
        <div class="fixed inset-0 z-50 hidden place-items-center bg-black/50 p-4" data-popup="{{ $popup->id }}">
            <div class="relative max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                <button type="button" data-popup-close class="absolute end-2 top-2 grid size-8 place-items-center rounded-full bg-white/90 text-xl leading-none shadow" aria-label="{{ __('store.close') }}">&times;</button>
                <a href="{{ $popup->safeLink() ?: '#' }}"><img src="{{ $popup->imageUrl() }}" alt="{{ $popup->title }}" class="w-full"></a>
            </div>
        </div>
    @endif
</x-layouts.storefront>
