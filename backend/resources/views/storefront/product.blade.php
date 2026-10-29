@php
    $cur = current_tenant()->currency_symbol;
    $first = collect($picker['variants'])->firstWhere('in_stock', true) ?? ($picker['variants'][0] ?? null);
    $img = $product->images->first()?->url;
    $desc = \Illuminate\Support\Str::limit(strip_tags((string) ($product->meta_description ?: $product->description)), 160, '');
    $ld = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->name, 'description' => $desc, 'sku' => $product->sku,
        'image' => $product->images->map->url->values()->all(), 'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
        'offers' => ['@type' => 'Offer', 'priceCurrency' => current_tenant()->currency_code, 'price' => $first['price'] ?? (string) $tp->effectivePrice(),
            'availability' => ($first['in_stock'] ?? false) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock', 'url' => url()->current()]];
    if ($summary['count']) { $ld['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => $summary['avg'], 'reviewCount' => $summary['count']]; }
    $ld = array_filter($ld, fn ($v) => $v !== null);
@endphp
<x-layouts.storefront :title="$product->meta_title ?: $product->name" :description="$desc" :image="$img" type="product" :jsonld="$ld">
    <nav class="mb-4 flex flex-wrap items-center gap-1 text-sm text-slate-500" aria-label="breadcrumb">
        <a href="{{ route('storefront.home') }}" class="hover:text-brand-600">{{ __('store.breadcrumb_home') }}</a>
        @foreach ($crumbs as $c)<span>/</span><a href="{{ route('storefront.category.show', $c->slug) }}" class="hover:text-brand-600">{{ $c->name }}</a>@endforeach
        <span>/</span><span class="font-semibold text-slate-800">{{ $product->name }}</span>
    </nav>

    <div class="grid gap-8 rounded-2xl bg-white p-4 ring-1 ring-slate-100 lg:grid-cols-2" data-product-page data-product-id="{{ $product->id }}" data-picker="{{ json_encode($picker, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS) }}">
        <div>
            <div class="overflow-hidden rounded-xl bg-slate-50">
                @if ($img)<img src="{{ $img }}" alt="{{ $product->name }}" class="aspect-square w-full object-contain" data-main-image>@else<div class="aspect-square bg-slate-100"></div>@endif
            </div>
            @if ($product->images->count() > 1)
                <div data-scroll-row class="mt-3 flex gap-2 overflow-x-auto">
                    @foreach ($product->images as $im)<button type="button" data-thumb="{{ $im->url }}" class="size-16 shrink-0 overflow-hidden rounded-lg border-2 {{ $loop->first ? 'border-brand-600' : 'border-transparent' }}"><img src="{{ $im->thumb_url }}" alt="" class="size-full object-cover"></button>@endforeach
                </div>
            @endif
        </div>

        <div class="space-y-4">
            @if ($product->brand)<a href="{{ route('storefront.search', ['brands' => [$product->brand->id]]) }}" class="text-sm font-semibold text-brand-600">{{ $product->brand->name }}</a>@endif
            <h1 class="text-2xl font-extrabold">{{ $product->name }}</h1>
            @if ($summary['count'])<p class="text-sm text-amber-500">{{ str_repeat('★', (int) round($summary['avg'])) }}<span class="text-slate-300">{{ str_repeat('★', 5 - (int) round($summary['avg'])) }}</span> <a href="#reviews" class="text-slate-500">{{ $summary['avg'] }} ({{ __('store.reviews_count', ['n' => $summary['count']]) }})</a></p>@endif

            <div class="flex flex-wrap items-baseline gap-3">
                <span class="text-3xl font-extrabold text-brand-600" data-price>{{ number_format((float) ($first['price'] ?? $tp->effectivePrice()), 2) }}</span><span class="font-semibold text-brand-600">{{ $cur }}</span>
                <span class="text-base text-slate-400 line-through {{ ($first['old'] ?? null) ? '' : 'hidden' }}" data-old>{{ ($first['old'] ?? null) ? number_format((float) $first['old'], 2) : '' }}</span>
            </div>
            <p class="hidden text-sm text-brand-600" data-flash>{{ __('store.ends_in') }} <span data-flash-time class="font-mono font-bold"></span></p>
            <p class="text-sm font-semibold" data-stock></p>
            <p class="text-xs text-slate-500"><span>{{ __('store.sku') }}: <b data-sku>{{ $first['sku'] ?? $product->sku }}</b></span> &middot; <span>{{ __('store.barcode') }}: <b data-barcode>{{ $first['barcode'] ?? $product->barcode }}</b></span></p>

            @foreach ($picker['options'] as $opt)
                <div data-option-group="{{ $opt['id'] }}">
                    <p class="mb-1 text-sm font-bold">{{ $opt['name'] }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($opt['values'] as $v)
                            <button type="button" data-option="{{ $v['id'] }}" class="inline-flex items-center gap-1 rounded-lg border-2 border-slate-200 px-3 py-1.5 text-sm transition disabled:cursor-not-allowed disabled:opacity-40 [&.is-active]:border-brand-600 [&.is-active]:bg-brand-50">
                                @if ($v['color'])<i class="size-4 rounded-full border border-slate-300" style="background: {{ $v['color'] }}"></i>@endif{{ $v['label'] }}</button>
                        @endforeach
                    </div>
                </div>
            @endforeach
            @if ($picker['variable'])<div class="hidden rounded-xl border border-slate-200 bg-white p-3" data-variant-status></div>@endif
            <p class="hidden text-sm text-red-600" data-combo-error>{{ __('store.unavailable_combo') }}</p>

            <div class="flex flex-wrap items-end gap-3 pt-2">
                <label class="text-sm font-semibold">{{ __('store.qty') }}<input type="number" min="1" value="1" class="form-control mt-1 w-24" data-qty></label>
                <button type="button" class="btn btn-primary !px-8 !py-3" data-add-to-cart-page>{{ __('store.add_to_cart') }}</button>
                <button type="button" class="btn btn-secondary hidden !px-8 !py-3" data-stock-request-page data-product="{{ $product->id }}" data-name="{{ $product->name }}">{{ __('store.request_now') }}</button>
                @auth('web')<button type="button" data-wish="{{ route('storefront.wishlist.toggle', $product->id) }}" class="btn btn-secondary !py-3" aria-label="{{ __('store.wishlist') }}">{{ $inWishlist ? '♥' : '♡' }} {{ __('store.wishlist') }}</button>
                @else<a href="{{ route('storefront.login') }}" class="btn btn-secondary !py-3" title="{{ __('store.wishlist_login') }}">♡ {{ __('store.wishlist') }}</a>@endauth
            </div>

            @if ($product->description)
                <div class="border-t border-slate-100 pt-4"><h2 class="mb-2 font-bold">{{ __('store.description') }}</h2><div class="text-sm leading-7 text-slate-600">{!! nl2br(e($product->description)) !!}</div></div>
            @endif
        </div>
    </div>

    <section id="reviews" class="mt-8 rounded-2xl bg-white p-4 ring-1 ring-slate-100">
        <h2 class="mb-3 text-lg font-extrabold">{{ __('growth.reviews') }}</h2>
        @forelse ($reviews as $r)
            <div class="border-t border-slate-100 py-3 text-sm"><span class="text-amber-500">{{ str_repeat('★', $r->rating) }}<span class="text-slate-300">{{ str_repeat('★', 5 - $r->rating) }}</span></span> <strong>{{ $r->customer?->name }}</strong><p class="mt-1 text-slate-600">{{ $r->body }}</p></div>
        @empty
            <p class="text-sm text-slate-500">{{ __('growth.no_reviews') }}</p>
        @endforelse
        @if ($canReview)
            <x-card class="mt-4 max-w-lg" :title="__('growth.write_review')">
                @if ($myReview)<p class="mb-2 text-xs text-slate-500">{{ __('growth.your_review_status', ['status' => $myReview->status->label()]) }}</p>@endif
                <form data-ajax-form data-validate-url="{{ route('storefront.reviews.validate', $product->slug) }}" action="{{ route('storefront.reviews.store', $product->slug) }}" method="POST" class="space-y-3" novalidate>
                    @csrf
                    <x-select name="rating" :label="__('growth.fields.rating')" :options="[5 => '★★★★★', 4 => '★★★★', 3 => '★★★', 2 => '★★', 1 => '★']" :value="$myReview?->rating ?? 5" />
                    <x-textarea name="body" :label="__('growth.fields.body')" :value="$myReview?->body" />
                    <button class="btn btn-primary">{{ __('growth.submit_review') }}</button>
                </form>
            </x-card>
        @endif
    </section>

    <x-rail :title="__('growth.related')" :items="$related" />
</x-layouts.storefront>
