{{-- Product gallery with at least one video (P8): one native scroll-snap slider of images + videos; resources/js/lib/product-gallery.js plays the active video. Products with images only keep the plain view in product.blade.php. --}}
<div data-product-gallery class="mx-auto w-full max-w-[480px]" role="group" aria-roledescription="carousel" aria-label="{{ $product->name }}">
    <div class="relative">
        <div data-gallery-track tabindex="0" class="flex aspect-square w-full snap-x snap-mandatory overflow-x-auto overflow-y-hidden overscroll-x-contain rounded-xl bg-slate-50 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach ($media as $m)
                <div data-gallery-slide data-type="{{ $m['type'] }}" data-url="{{ $m['url'] }}" role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} / {{ $media->count() }}" class="relative size-full shrink-0 snap-center snap-always">
                    @if ($m['type'] === 'video')
                        {{-- muted + playsinline: browsers only autoplay silent inline video. Only the first slide may fetch metadata up front. --}}
                        <video data-gallery-video src="{{ $m['url'] }}" @if ($m['poster']) poster="{{ $m['poster'] }}" @endif muted playsinline loop controls controlslist="nodownload" preload="{{ $loop->first ? 'metadata' : 'none' }}" aria-label="{{ __('store.product_video', ['name' => $product->name]) }}" class="size-full bg-slate-900 object-contain"></video>
                        <button type="button" data-gallery-play class="absolute inset-0 m-auto hidden size-16 place-items-center rounded-full bg-black/60 text-white [&:not(.hidden)]:grid" aria-label="{{ __('store.play_video') }}"><svg viewBox="0 0 24 24" class="size-8 fill-current" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></button>
                    @else
                        <x-media-img :src="$m['url']" :alt="$product->name" width="800" height="800" :eager="$loop->first" class="size-full object-cover" />
                    @endif
                </div>
            @endforeach
            @if ($product->isVariable())
                {{-- shows a variant's own image when it is not one of the product's slides --}}
                <div data-gallery-slide data-type="variant" hidden class="relative size-full shrink-0 snap-center snap-always"><img alt="{{ $product->name }}" class="size-full object-cover"></div>
            @endif
        </div>
        @if ($media->count() > 1)
            <button type="button" data-gallery-prev class="absolute start-2 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-full bg-white/85 text-slate-800 shadow" aria-label="{{ __('store.prev') }}"><svg viewBox="0 0 24 24" class="size-5 fill-none stroke-current stroke-2 rtl:rotate-180" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></button>
            <button type="button" data-gallery-next class="absolute end-2 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-full bg-white/85 text-slate-800 shadow" aria-label="{{ __('store.next') }}"><svg viewBox="0 0 24 24" class="size-5 fill-none stroke-current stroke-2 rtl:rotate-180" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></button>
        @endif
    </div>
    @if ($media->count() > 1)
        <div data-scroll-row class="mx-auto mt-3 flex max-w-[480px] gap-2 overflow-x-auto">
            @foreach ($media as $m)
                <button type="button" data-gallery-thumb="{{ $loop->index }}" data-type="{{ $m['type'] }}" aria-label="{{ $product->name }} {{ $loop->iteration }}" @if ($loop->first) aria-current="true" @endif class="relative size-16 shrink-0 overflow-hidden rounded-lg border-2 {{ $loop->first ? 'border-brand-600' : 'border-transparent' }}">
                    @if ($m['thumb'])<img src="{{ $m['thumb'] }}" alt="" class="size-full object-cover">@else<span class="block size-full bg-slate-800"></span>@endif
                    @if ($m['type'] === 'video')<span data-video-badge class="absolute inset-0 grid place-items-center"><span class="grid size-7 place-items-center rounded-full bg-black/60 text-white"><svg viewBox="0 0 24 24" class="size-4 fill-current" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></span></span>@endif
                </button>
            @endforeach
        </div>
    @endif
</div>
