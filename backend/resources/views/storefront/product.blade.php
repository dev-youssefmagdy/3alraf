@php
    $cur = setting('currency_symbol');
    $first = collect($picker['variants'])->firstWhere('in_stock', true) ?? ($picker['variants'][0] ?? null);
    $img = $product->images->first()?->url;
    $preload = ($media->first()['type'] ?? null) === 'video' ? $media->first()['poster'] : $img;   // the first slide is what must paint first
    $desc = \Illuminate\Support\Str::limit(strip_tags((string) ($product->meta_description ?: $product->description)), 160, '');
    $ld = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->name, 'description' => $desc, 'sku' => $product->sku,
        'image' => $product->images->map->url->values()->all(), 'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
        'url' => url()->current(), 'mpn' => $product->sku,
        'offers' => ['@type' => 'Offer', 'priceCurrency' => setting('currency_code'), 'price' => number_format((float) ($first['price'] ?? $product->effectivePrice()), 2, '.', ''),
            'priceValidUntil' => now()->addYear()->toDateString(), 'itemCondition' => 'https://schema.org/NewCondition',
            'availability' => ($first['in_stock'] ?? false) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock', 'url' => url()->current(),
            'seller' => ['@type' => 'Organization', 'name' => setting('name')]]];
    $bc = array_merge([[__('store.breadcrumb_home'), route('storefront.home')]], collect($crumbs)->map(fn ($c) => [$c->name, route('storefront.category.show', $c->slug)])->all(), [[$product->name, url()->current()]]);
    if ($summary['count']) { $ld['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => $summary['avg'], 'reviewCount' => $summary['count']]; }
    $ld = array_filter($ld, fn ($v) => $v !== null);
    $price = (float) ($first['price'] ?? $product->effectivePrice());
    $old = ($first['old'] ?? null) ? (float) $first['old'] : null;
    $avg = (int) round((float) $summary['avg']);
    $stars = fn (int $n, string $size = 'text-base') => '<span class="'.$size.' tracking-[-0.1em] text-sun-400" aria-hidden="true">'.str_repeat('★', $n).'<span class="text-slate-200">'.str_repeat('★', 5 - $n).'</span></span>';
    $cart = '<svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h8.8a1 1 0 0 0 1-.8L20 8H6M9 20h.01M17 20h.01"/></svg>';
    $heart = fn (bool $on) => '<svg class="size-5" viewBox="0 0 24 24" fill="'.($on ? 'currentColor' : 'none').'" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M12 20s-7-4.4-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.6-9 9-9 9z"/></svg>';
@endphp
<x-layouts.storefront :title="$product->meta_title ?: $product->name" :description="$desc" :image="$img" :preload-image="$preload" :breadcrumbs="$bc" type="product" :jsonld="$ld">
    <x-breadcrumbs :items="$bc" />

    {{-- design 24:3881 / 24:4395: gallery (start) + buy box (end) --}}
    <div data-product-page data-product-id="{{ $product->id }}" data-picker="{{ json_encode($picker, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS) }}">
        <div class="grid gap-6 lg:grid-cols-2 lg:gap-10">
            <div class="min-w-0 lg:sticky lg:top-40 lg:self-start">@include('storefront._product-gallery')</div>

            <div class="min-w-0 space-y-4">
                @if ($product->brand)<a href="{{ route('storefront.search', ['brands' => [$product->brand->id]]) }}" class="inline-flex min-h-8 items-center rounded-full bg-pop-50 px-3 text-xs font-bold text-pop-600 hover:bg-pop-100">{{ $product->brand->name }}</a>@endif
                <h1 class="font-display text-[28px]/9 font-bold text-slate-950 lg:text-[32px]/10">{{ $product->name }}</h1>
                @if ($summary['count'])<p class="flex items-center gap-2 text-sm">{!! $stars($avg) !!}<a href="#reviews" class="font-semibold text-slate-600 hover:text-brand-600"><span class="sr-only">{{ __('store.avg_rating') }}: </span>{{ $summary['avg'] }} <span class="sr-only">/ 5</span>({{ __('store.reviews_count', ['n' => $summary['count']]) }})</a></p>@endif

                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <span class="price text-[30px]/9 {{ $old ? 'price-sale' : 'text-brand-600' }}" data-price-wrap><span data-price>{{ number_format($price, 2) }}</span> <small class="font-sans text-base font-bold">{{ $cur }}</small></span>
                    <span class="price-old !text-sm {{ $old ? '' : 'hidden' }}" data-old>{{ $old ? number_format($old, 2) : '' }}</span>
                    <span class="badge bg-sun-400 !text-xs text-slate-950 {{ $old ? '' : 'hidden' }}" data-save data-save-tpl="{{ __('store.bundle_save', ['amount' => ':amount '.$cur]) }}">{{ $old ? __('store.bundle_save', ['amount' => number_format($old - $price, 2).' '.$cur]) : '' }}</span>
                </div>
                <p class="hidden w-fit items-center gap-2 rounded-full bg-pop-50 px-3 py-1.5 text-sm font-bold text-pop-600 [&:not(.hidden)]:inline-flex" data-flash>
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2"/></svg>
                    {{ __('store.ends_in') }} <span data-flash-time class="tabular-nums" dir="ltr"></span></p>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <p class="text-sm font-bold" data-stock></p>
                    <p class="text-xs text-slate-500"><span>{{ __('store.sku') }}: <bdi data-sku>{{ $first['sku'] ?? $product->sku }}</bdi></span> &middot; <span>{{ __('store.barcode') }}: <bdi data-barcode>{{ $first['barcode'] ?? $product->barcode }}</bdi></span></p>
                </div>

                @foreach ($picker['options'] as $opt)
                    <div data-option-group="{{ $opt['id'] }}" role="group" aria-labelledby="opt-{{ $opt['id'] }}">
                        <p id="opt-{{ $opt['id'] }}" class="mb-2 text-sm font-bold text-slate-950">{{ $opt['name'] }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($opt['values'] as $v)
                                <button type="button" data-option="{{ $v['id'] }}" class="chip disabled:cursor-not-allowed disabled:border-dashed disabled:opacity-45 [&.is-active]:border-brand-600 [&.is-active]:bg-brand-600 [&.is-active]:text-white">
                                    @if ($v['color'])<i class="size-4 shrink-0 rounded-full border border-slate-300 ring-2 ring-white" style="background: {{ $v['color'] }}"></i>@endif{{ $v['label'] }}</button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                @if ($picker['variable'])<div class="hidden rounded-2xl border border-slate-200 bg-white p-4" data-variant-status></div>@endif
                <p class="hidden text-sm font-semibold text-red-600" data-combo-error role="alert">{{ __('store.unavailable_combo') }}</p>

                <div class="flex flex-wrap items-center gap-3 pt-1" data-buy-box>
                    <div dir="ltr" class="inline-flex h-13 items-center gap-1 rounded-full border border-slate-300 bg-white p-1">
                        <button type="button" data-qty-step="-1" class="grid size-11 place-items-center rounded-full bg-brand-50 text-brand-600 hover:bg-brand-100" aria-label="{{ __('store.qty_decrease') }}"><svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg></button>
                        <label class="sr-only" for="pd-qty">{{ __('store.qty') }}</label>
                        <input id="pd-qty" type="number" min="1" value="1" inputmode="numeric" class="w-12 border-0 bg-transparent p-0 text-center font-bold text-slate-950 [appearance:textfield] focus:ring-0 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none" data-qty>
                        <button type="button" data-qty-step="1" class="grid size-11 place-items-center rounded-full bg-brand-50 text-brand-600 hover:bg-brand-100" aria-label="{{ __('store.qty_increase') }}"><svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></button>
                    </div>
                    <button type="button" class="btn btn-primary btn-lg min-w-0 flex-1 max-sm:order-last max-sm:basis-full" data-add-to-cart-page>{!! $cart !!}{{ __('store.add_to_cart') }}</button>
                    <button type="button" class="btn btn-sun btn-lg hidden min-w-0 flex-1 max-sm:order-last max-sm:basis-full" data-stock-request-page data-product="{{ $product->id }}" data-name="{{ $product->name }}">{{ __('store.request_now') }}</button>
                    @auth('web')<button type="button" data-wish="{{ route('storefront.wishlist.toggle', $product->id) }}" aria-pressed="{{ $inWishlist ? 'true' : 'false' }}" class="icon-btn icon-btn-outline size-13 {{ $inWishlist ? '!text-pop-600' : '' }}" aria-label="{{ __('store.wishlist') }}" title="{{ __('store.wishlist') }}">{!! $heart((bool) $inWishlist) !!}</button>
                    @else<a href="{{ route('storefront.login') }}" class="icon-btn icon-btn-outline size-13" aria-label="{{ __('store.wishlist') }}" title="{{ __('store.wishlist_login') }}">{!! $heart(false) !!}</a>@endauth
                </div>
                @include('storefront._trust-trio')
            </div>
        </div>

        {{-- details: underline tabs (desktop anchors) → description → reviews --}}
        <nav class="mt-10 hidden border-b border-slate-200 lg:flex lg:gap-8" aria-label="{{ __('store.description') }}">
            @if ($product->description)<a href="#description" class="-mb-px border-b-2 border-brand-600 py-3 text-sm font-bold text-brand-600">{{ __('store.description') }}</a>@endif
            <a href="#reviews" class="-mb-px border-b-2 border-transparent py-3 text-sm font-bold text-slate-600 hover:text-brand-600">{{ __('store.reviews_tab') }} ({{ $summary['count'] }})</a>
        </nav>
        @if ($product->description)
            <section id="description" class="panel mt-6 scroll-mt-40 lg:p-6">
                <h2 class="mb-2 font-display text-[22px]/[30px] font-bold text-slate-950">{{ __('store.description') }}</h2>
                <div class="text-base/[26px] text-slate-600">{!! nl2br(e($product->description)) !!}</div>
            </section>
        @endif

        {{-- sticky add to cart (phones / tablets): price + button above the bottom bar, shown once the buy box scrolls away --}}
        <div data-sticky-buy class="fixed inset-x-0 bottom-[calc(72px+env(safe-area-inset-bottom))] z-30 translate-y-[150%] border-t border-slate-200 bg-white/95 px-4 py-2.5 shadow-bar backdrop-blur transition-transform duration-200 ease-(--ease-pop) data-[shown]:translate-y-0 lg:hidden" aria-hidden="true">
            <div class="mx-auto flex max-w-xl items-center gap-3">
                <span class="min-w-0 flex-1"><span class="block truncate text-xs text-slate-600">{{ $product->name }}</span><span class="price text-lg/6 text-brand-600"><span data-price>{{ number_format($price, 2) }}</span> <small class="font-sans text-xs font-bold">{{ $cur }}</small></span></span>
                <button type="button" class="btn btn-primary shrink-0" data-sticky-add tabindex="-1">{!! $cart !!}{{ __('store.add_to_cart') }}</button>
                <button type="button" class="btn btn-sun hidden shrink-0" data-sticky-request tabindex="-1">{{ __('store.request_now') }}</button>
            </div>
        </div>
    </div>

    <section id="reviews" class="panel mt-6 scroll-mt-40 lg:p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-display text-[22px]/[30px] font-bold text-slate-950">{{ __('growth.reviews') }}</h2>
            @if ($summary['count'])
                <div class="flex items-center gap-3 rounded-2xl bg-sun-100 px-4 py-2">
                    <span class="font-display text-[32px]/10 font-extrabold text-slate-950">{{ $summary['avg'] }}</span>
                    <span class="text-xs text-slate-600">{!! $stars($avg, 'text-sm') !!}<span class="block">{{ __('store.reviews_count', ['n' => $summary['count']]) }}</span></span>
                </div>
            @endif
        </div>
        @if ($reviews->isNotEmpty())
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($reviews as $r)
                    <article class="rounded-2xl border border-slate-200 p-4 text-sm">
                        <div class="flex items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-violet-100 font-bold text-violet-700" aria-hidden="true">{{ mb_substr($r->customer?->name ?? '?', 0, 1) }}</span>
                            <div class="min-w-0"><strong>{{ $r->customer?->name }}</strong><span class="block">{!! $stars((int) $r->rating, 'text-sm') !!}<span class="sr-only">{{ $r->rating }} / 5</span></span></div>
                        </div>
                        <p class="mt-2 text-slate-600">{{ $r->body }}</p>
                    </article>
                @endforeach
            </div>
        @else
            <p class="text-sm text-slate-600">{{ __('growth.no_reviews') }}</p>
        @endif
        @if ($canReview)
            <div class="mt-5 max-w-lg rounded-3xl bg-brand-50 p-4 sm:p-5">
                <h3 class="mb-3 text-lg/7 font-bold text-slate-950">{{ __('growth.write_review') }}</h3>
                @if ($myReview)<p class="mb-2 text-xs text-slate-600">{{ __('growth.your_review_status', ['status' => $myReview->status->label()]) }}</p>@endif
                <form data-ajax-form data-validate-url="{{ route('storefront.reviews.validate', $product->slug) }}" action="{{ route('storefront.reviews.store', $product->slug) }}" method="POST" class="space-y-3" novalidate>
                    @csrf
                    <x-select name="rating" :label="__('growth.fields.rating')" :options="[5 => '★★★★★', 4 => '★★★★', 3 => '★★★', 2 => '★★', 1 => '★']" :value="$myReview?->rating ?? 5" />
                    <x-textarea name="body" :label="__('growth.fields.body')" :value="$myReview?->body" />
                    <button class="btn btn-primary">{{ __('growth.submit_review') }}</button>
                </form>
            </div>
        @endif
    </section>

    <x-rail :title="__('growth.related')" :items="$related" />
    <script>window.sfTrack && sfTrack('view_item', {value: {{ (float) ($first['price'] ?? $product->effectivePrice()) }}, items: [{id: @js((string) $product->id), name: @js($product->name), price: {{ (float) ($first['price'] ?? $product->effectivePrice()) }}, brand: @js($product->brand?->name)}]});</script>
</x-layouts.storefront>
