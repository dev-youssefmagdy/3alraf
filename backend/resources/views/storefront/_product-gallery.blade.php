{{--
    Product gallery (design 24:3881 / 24:4395) on the Swiper "gallery" preset (resources/js/lib/sliders.js): images and videos in one main
    slider + thumbs (a row below on phones, a vertical column at the start side from 1024px), "1 / 5" counter, arrows on the image ≥1024.
    The active slide's video plays muted + inline; leaving it pauses and rewinds; a refused autoplay / reduced motion shows [data-gallery-play].
    A variant image that is not one of the slides is appended as an extra slide by the gallery API (root.gallery.showVariantImage).
--}}
@php
    $n = $media->count();
    $badgeOff = ($first['old'] ?? null) && ($first['price'] ?? null) ? (int) round((1 - (float) $first['price'] / (float) $first['old']) * 100) : 0;
@endphp
<div data-swiper="gallery" class="product-gallery lg:grid lg:grid-cols-[88px_minmax(0,1fr)] lg:gap-3" role="group" aria-roledescription="carousel" aria-label="{{ __('store.gallery') }}: {{ $product->name }}">
    <div class="relative lg:col-start-2 lg:row-start-1">
        <div class="swiper overflow-hidden rounded-3xl bg-slate-100 lg:rounded-[32px]" data-swiper-main>
            <div class="swiper-wrapper">
                @forelse ($media as $m)
                    <div class="swiper-slide" data-gallery-slide data-type="{{ $m['type'] }}" data-url="{{ $m['url'] }}" role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} / {{ $n }}">
                        @if ($m['type'] === 'video')
                            {{-- muted + playsinline: browsers only autoplay silent inline video. Only the first slide may fetch metadata up front. --}}
                            <video data-gallery-video src="{{ $m['url'] }}" @if ($m['poster']) poster="{{ $m['poster'] }}" @endif muted playsinline loop controls controlslist="nodownload" preload="{{ $loop->first ? 'metadata' : 'none' }}" aria-label="{{ __('store.product_video', ['name' => $product->name]) }}" class="aspect-square size-full bg-slate-950 object-contain"></video>
                            <button type="button" data-gallery-play class="absolute inset-0 z-10 m-auto hidden size-16 place-items-center rounded-full bg-slate-950/65 text-white [&:not(.hidden)]:grid" aria-label="{{ __('store.play_video') }}"><svg viewBox="0 0 24 24" class="size-8 fill-current" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></button>
                        @else
                            <x-media-img :src="$m['url']" :alt="$product->name" width="800" height="800" :eager="$loop->first" draggable="false" class="aspect-square size-full object-cover" />
                        @endif
                    </div>
                @empty
                    <div class="swiper-slide"><div class="aspect-square size-full bg-slate-100"></div></div>
                @endforelse
            </div>
        </div>
        @if ($badgeOff > 0)<span class="badge badge-sale pointer-events-none absolute start-4 top-4 z-10">{{ __('store.off', ['p' => $badgeOff]) }}</span>@endif
        <button type="button" data-share-url="{{ url()->current() }}" data-share-title="{{ $product->name }}" class="icon-btn icon-btn-glass absolute end-4 top-4 z-10" aria-label="{{ __('store.share') }}">
            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg>
        </button>
        @if ($n > 1)
            <button type="button" data-swiper-prev data-gallery-prev class="icon-btn icon-btn-glass absolute start-4 top-1/2 z-10 -translate-y-1/2" aria-label="{{ __('store.prev') }}"><svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg></button>
            <button type="button" data-swiper-next data-gallery-next class="icon-btn icon-btn-glass absolute end-4 top-1/2 z-10 -translate-y-1/2" aria-label="{{ __('store.next') }}"><svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></button>
            <span data-swiper-pagination dir="ltr" class="pointer-events-none absolute bottom-4 start-4 z-10 !w-auto rounded-full bg-slate-950/70 px-3 py-1 text-xs font-bold tabular-nums text-white"></span>
        @endif
    </div>
    @if ($n > 1)
        <div class="relative mt-3 lg:col-start-1 lg:row-start-1 lg:mt-0">
            <div class="swiper lg:!absolute lg:inset-0" data-swiper-thumbs>
                <div class="swiper-wrapper">
                    @foreach ($media as $m)
                        <div class="swiper-slide">
                            <button type="button" data-gallery-thumb="{{ $loop->index }}" data-type="{{ $m['type'] }}" tabindex="-1" aria-label="{{ $product->name }} {{ $loop->iteration }}" class="relative block aspect-square w-full overflow-hidden rounded-xl bg-slate-100">
                                @if ($m['thumb'])<img src="{{ $m['thumb'] }}" alt="" class="size-full object-cover" loading="lazy" decoding="async" width="96" height="96">@else<span class="block size-full bg-slate-800"></span>@endif
                                @if ($m['type'] === 'video')<span data-video-badge class="absolute inset-0 grid place-items-center"><span class="grid size-7 place-items-center rounded-full bg-slate-950/65 text-white"><svg viewBox="0 0 24 24" class="size-4 fill-current" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></span></span>@endif
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
