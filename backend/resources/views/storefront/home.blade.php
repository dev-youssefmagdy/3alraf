<x-layouts.storefront :title="setting('name')" :description="__('store.seo_default_desc')" :preload-image="$hero->first()?->imageUrl()" :jsonld="['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => setting('name'), 'url' => url('/'.app()->getLocale()),
    'potentialAction' => ['@type' => 'SearchAction', 'target' => route('storefront.search').'?q={q}', 'query-input' => 'required name=q']]">
    @php
        // design 23:2 / 23:1059: every horizontal row is a Swiper preset (resources/js/lib/sliders.js)
        $rings = ['ring-brand-600', 'ring-pop-500', 'ring-sun-400', 'ring-mint-400', 'ring-violet-500'];
        $tileTints = ['bg-pop-600', 'bg-violet-500', 'bg-mint-400', 'bg-sun-400', 'bg-brand-800'];
        $giftOn = app(\App\Services\GiftFinder\GiftFinderSettings::class)->enabled();
    @endphp
    <h1 class="sr-only">{{ (app(\App\Services\SeoSettingsService::class)->current()['home_title'][app()->getLocale()] ?? '') ?: setting('name') }}</h1>

    {{-- hero banners (19:477): CMS images fill the slide; autoplay with a pause toggle, off under reduced motion --}}
    @if ($hero->isNotEmpty())
        <section data-swiper="hero" class="group/hero relative" aria-roledescription="carousel" aria-label="{{ __('store.featured_offers') }}">
            <div class="swiper overflow-hidden rounded-3xl bg-brand-800 lg:rounded-[32px]">
                <div class="swiper-wrapper">
                    @foreach ($hero as $b)
                        <div class="swiper-slide" role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} / {{ $hero->count() }}">
                            <a href="{{ $b->safeLink() ?: '#' }}" class="block">
                                @if ($b->imageUrl())<x-media-img :src="$b->imageUrl()" :alt="$b->title" width="1600" height="550" :eager="$loop->first" draggable="false" class="aspect-[16/9] w-full object-cover sm:aspect-[2/1] lg:aspect-[1280/440]" />
                                @else<span class="grid aspect-[16/9] w-full place-items-center p-6 text-center font-display text-[32px]/[40px] font-extrabold text-white sm:aspect-[2/1] lg:aspect-[1280/440] lg:text-[56px]/[64px]">{{ $b->title }}</span>@endif
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
            @if ($hero->count() > 1)
                <x-slider-arrows style="icon-btn-glass" class="absolute top-1/2 z-10 -translate-y-1/2 data-[swiper-prev]:start-4 data-[swiper-next]:end-4" />
                <div class="absolute inset-x-0 bottom-3 z-10 flex items-center justify-center gap-2 lg:bottom-5">
                    <div class="rounded-full bg-slate-950/35 px-2.5 py-1.5 backdrop-blur-sm"><div data-swiper-pagination></div></div>
                    <button type="button" data-swiper-toggle data-label-pause="{{ __('store.pause_slides') }}" data-label-play="{{ __('store.play_slides') }}" aria-label="{{ __('store.pause_slides') }}"
                        class="hero-toggle grid size-8 place-items-center rounded-full bg-slate-950/35 text-white backdrop-blur-sm hover:bg-slate-950/55">
                        <svg class="hero-toggle-pause size-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5h3v14H7zM14 5h3v14h-3z"/></svg>
                        <svg class="hero-toggle-play size-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                    </button>
                </div>
            @endif
        </section>
    @endif

    {{-- shop by category (8:538): ringed circles, 4.3 / 6.3 / 8 per view --}}
    @if (count($categories))
        <section data-swiper="categories" data-swiper-bleed class="mt-7 max-lg:-mx-4 lg:mt-12">
            <x-slider-head :title="__('store.top_categories')" class="max-lg:px-4" />
            <div class="swiper">
                <div class="swiper-wrapper">
                    @foreach ($categories as $c)
                        <div class="swiper-slide">
                            <a href="{{ route('storefront.category.show', $c['slug']) }}" class="group block text-center">
                                <span class="mx-auto grid aspect-square w-full place-items-center overflow-hidden rounded-full bg-brand-50 ring-4 ring-inset {{ $rings[$loop->index % 5] }} p-1 transition group-hover:scale-[1.03]">
                                    @if ($c['image'])<img src="{{ $c['image'] }}" alt="" class="size-full rounded-full object-cover" loading="lazy" decoding="async" width="96" height="96">
                                    @else<span class="font-display text-2xl font-extrabold text-brand-600">{{ mb_substr($c['name'], 0, 1) }}</span>@endif
                                </span>
                                <span class="mt-2 line-clamp-2 block text-xs/4 font-bold text-slate-950 group-hover:text-brand-600 lg:text-sm/5">{{ $c['name'] }}</span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- promo strips (wide CMS banners) and tiles (19:526): the whole tile is the link --}}
    @if ($strips->isNotEmpty())
        <section data-swiper="promo" data-promo="strip" data-swiper-options='{"slidesPerView":1.08,"breakpoints":{"768":{"slidesPerView":2,"spaceBetween":16},"1024":{"slidesPerView":2,"spaceBetween":20}}}' class="mt-7 lg:mt-12" aria-label="{{ __('store.featured_offers') }}">
            <div class="swiper">
                <div class="swiper-wrapper">
                    @foreach ($strips as $b)
                        <div class="swiper-slide"><a href="{{ $b->safeLink() ?: '#' }}" class="block overflow-hidden rounded-2xl {{ $tileTints[$loop->index % 5] }} lg:rounded-3xl">@if ($b->imageUrl())<img src="{{ $b->imageUrl() }}" alt="{{ $b->title }}" width="1600" height="300" class="product-card-img aspect-[16/3] w-full object-cover" loading="lazy" decoding="async">@else<span class="block aspect-[16/3] p-4 font-display text-lg font-bold text-white">{{ $b->title }}</span>@endif</a></div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
    @if ($tiles->isNotEmpty())
        <section data-swiper="promo" class="mt-4 lg:mt-6" aria-label="{{ __('store.featured_offers') }}">
            <div class="swiper">
                <div class="swiper-wrapper">
                    @foreach ($tiles as $b)
                        <div class="swiper-slide">
                            <a href="{{ $b->safeLink() ?: '#' }}" class="group relative block overflow-hidden rounded-3xl {{ $tileTints[$loop->index % 5] }} lg:rounded-[32px]">
                                @if ($b->imageUrl())<img src="{{ $b->imageUrl() }}" alt="{{ $b->title }}" width="800" height="400" class="product-card-img aspect-[2/1] w-full object-cover lg:aspect-[413/180]" loading="lazy" decoding="async">
                                @else<span class="block aspect-[2/1] lg:aspect-[413/180]"></span>@endif
                                <span class="absolute bottom-3 start-3 inline-flex items-center gap-1 rounded-full bg-white px-3 py-1.5 text-xs font-bold text-slate-950 shadow-raised">{{ __('store.shop_now') }}<svg class="size-3.5 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- flash sale: pop block with the countdown (rail "flash" preset) --}}
    @if ($flash)
        <x-rail :title="$flash['sale']->name" :items="$flash['items']" :countdown="$flash['sale']->ends_at->toIso8601String()" />
    @endif

    {{-- gift finder CTA (19:558) --}}
    @if ($giftOn)
        <a href="{{ route('storefront.gift-finder.show') }}" data-home-gift-finder class="group relative mt-8 flex items-center gap-4 overflow-hidden rounded-3xl bg-violet-500 p-5 text-white lg:mt-12 lg:rounded-[32px] lg:px-10 lg:py-8">
            <span class="pointer-events-none absolute -bottom-16 -end-10 size-48 rounded-full bg-pop-600/70" aria-hidden="true"></span>
            <span class="pointer-events-none absolute -top-6 end-1/3 size-14 rounded-full border-[6px] border-sun-400 max-sm:hidden" aria-hidden="true"></span>
            <span class="relative grid size-14 shrink-0 place-items-center rounded-full bg-sun-400 text-slate-950 shadow-raised lg:size-20"><svg class="size-7 lg:size-9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"/></svg></span>
            <span class="relative min-w-0 flex-1">
                <span class="block font-display text-xl/7 font-extrabold lg:text-[28px]/9">{{ __('gift_finder.cta') }}</span>
                <span class="mt-1 block text-sm/[22px] text-white/90">{{ __('gift_finder.intro') }}</span>
                <span class="btn btn-sun mt-3 inline-flex sm:hidden">{{ __('gift_finder.find') }}</span>
            </span>
            <span class="btn btn-sun btn-lg relative hidden shrink-0 sm:inline-flex">{{ __('gift_finder.find') }}</span>
        </a>
    @endif

    {{-- bundles (8:527) on the rail preset --}}
    @if (! empty($bundles))
        <section data-swiper="rail" data-swiper-bleed class="mt-8 max-lg:-mx-4 lg:mt-12">
            <x-slider-head :title="__('store.bundles')" :href="route('storefront.bundles.index')" class="max-lg:px-4" />
            <div class="swiper !py-1">
                <div class="swiper-wrapper">
                    @foreach ($bundles as $b)<div class="swiper-slide">@include('storefront._bundle-card', ['b' => $b])</div>@endforeach
                </div>
            </div>
            <div data-swiper-pagination class="mx-auto mt-4 w-32"></div>
        </section>
    @endif

    <x-rail :title="__('store.recommended')" :items="$rails['recommended']" />
    <x-rail :title="__('store.most_sold')" :items="$rails['most_sold']" />
    <x-rail :title="__('store.most_reviewed')" :items="$rails['most_reviewed']" />

    @foreach ($categoryRails as $r)
        <x-rail :title="$r['category']->name" :items="$r['items']" :href="route('storefront.category.show', $r['category']->slug)" />
    @endforeach

    {{-- brand strip (8:546): white panel + Swiper --}}
    @if ($brands->isNotEmpty())
        <section data-swiper="brands" class="panel mt-8 lg:mt-12 lg:rounded-[32px] lg:p-8">
            <x-slider-head :title="__('store.brands')" :href="route('storefront.brands.index')" />
            <div class="swiper !py-1">
                <div class="swiper-wrapper">
                    @foreach ($brands as $br)
                        <div class="swiper-slide">@include('storefront._brand-tile', ['br' => $br, 'i' => $loop->index])</div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- popup banner (modal 21:1844): a bottom sheet on phones, centred on desktop --}}
    @if ($popup && $popup->imageUrl())
        <div class="fixed inset-0 z-50 hidden items-end justify-center bg-black/50 sm:place-items-center sm:p-4" data-popup="{{ $popup->id }}" role="dialog" aria-modal="true" aria-label="{{ $popup->title }}">
            <div class="relative w-full overflow-hidden rounded-t-3xl bg-white shadow-overlay sm:max-w-md sm:rounded-3xl">
                <button type="button" data-popup-close class="icon-btn icon-btn-glass absolute end-3 top-3 z-10" aria-label="{{ __('store.close') }}"><svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
                <a href="{{ $popup->safeLink() ?: '#' }}"><img src="{{ $popup->imageUrl() }}" alt="{{ $popup->title }}" class="w-full"></a>
            </div>
        </div>
    @endif
</x-layouts.storefront>
