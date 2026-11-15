@props(['product', 'eager' => false])
@php
    $p = $product;
    $d = $p->display ?? app(\App\Services\Storefront\CardPresenter::class)->decorate(collect([$p]))->first()->display;
    $url = route('storefront.product.show', ['locale' => app()->getLocale(), 'slug' => $p->slug]);
    static $wished = null;
    $customer = auth('web')->user();
    $wished ??= $customer ? \App\Models\WishlistItem::where('customer_id', $customer->id)->pluck('product_id')->all() : [];
    $fav = in_array($p->id, $wished, true);
    // media tints rotate mint / pop / sun / violet (design 8:454)
    $tint = ['bg-mint-100', 'bg-pop-50', 'bg-sun-100', 'bg-violet-100'][$p->id % 4];
    $stars = $d['rating'] ? (int) round($d['rating']) : 0;
@endphp
<div {{ $attributes->merge(['class' => 'product-card group relative flex flex-col rounded-2xl border border-slate-200 bg-white p-2 shadow-card']) }} data-product-card>
    <div class="relative">
        <a href="{{ $url }}" class="block overflow-hidden rounded-xl {{ $tint }}">
            @if ($img = $p->primaryImageUrl())<x-media-img :src="$img" :alt="$p->name" width="400" height="400" :eager="(bool) $eager" class="product-card-img aspect-square w-full object-cover {{ $d['out'] ? 'opacity-60 grayscale' : '' }}" />
            @else<div class="aspect-square w-full"></div>@endif
        </a>
        <div class="pointer-events-none absolute start-2 top-2 z-10 flex flex-col items-start gap-1">
            @if ($d['percent'])<span class="badge badge-sale">{{ __('store.off', ['p' => $d['percent']]) }}</span>@endif
            @if ($d['out'])<span class="badge badge-out">{{ __('store.out_of_stock') }}</span>@endif
        </div>
        <button type="button" data-card-wish="{{ $customer ? route('storefront.wishlist.toggle', ['locale' => app()->getLocale(), 'product' => $p->id]) : '' }}" data-login="{{ route('storefront.login', ['locale' => app()->getLocale()]) }}" aria-pressed="{{ $fav ? 'true' : 'false' }}" aria-label="{{ __('store.wishlist') }}"
            class="card-wish absolute end-2 top-2 z-10 grid size-9 place-items-center rounded-full bg-white shadow-raised transition hover:scale-110 {{ $fav ? 'text-red-500' : 'text-slate-400' }}">
            <svg viewBox="0 0 24 24" class="size-[18px]" fill="{{ $fav ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M12 20s-7-4.4-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.6-9 9-9 9z"/></svg>
        </button>
    </div>
    <div class="flex flex-1 flex-col px-1 pb-1">
        <a href="{{ $url }}" class="mt-2 line-clamp-2 min-h-11 text-sm/[22px] font-semibold text-slate-950 hover:text-brand-600">{{ $p->name }}</a>
        @if ($d['rating'])
            <p class="mt-1 flex items-center gap-1 text-xs text-slate-500">
                <span class="tracking-[-0.1em] text-sun-400" aria-hidden="true">{{ str_repeat('★', $stars) }}<span class="text-slate-200">{{ str_repeat('★', 5 - $stars) }}</span></span>
                <span class="sr-only">{{ round((float) $d['rating'], 1) }} / 5</span>
                <span>({{ $d['reviews'] }})</span>
            </p>
        @endif
        <div class="mt-1 flex flex-wrap items-baseline gap-x-2">
            <span class="price text-lg/6 {{ $d['old'] || $d['flash'] ? 'price-sale' : '' }}">@if ($d['from'] ?? false)<small class="font-sans text-xs font-normal text-slate-500">{{ __('store.from_price') }}</small> @endif{{ number_format((float) $d['price'], 2) }} <small class="font-sans text-xs font-bold">{{ setting('currency_symbol') }}</small></span>
            @if ($d['old'])<span class="price-old">{{ number_format((float) $d['old'], 2) }}</span>@endif
        </div>
        @if ($d['flash'] && $d['ends_at'])
            <p class="mt-1.5 inline-flex w-fit max-w-full items-center gap-1 whitespace-nowrap rounded-full bg-pop-50 px-2 py-0.5 text-[11px] font-bold text-pop-600">
                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2"/></svg>
                <span class="max-sm:sr-only">{{ __('store.ends_in') }}</span> <span data-countdown="{{ $d['ends_at'] }}" class="tabular-nums"></span>
            </p>
        @endif
        @if ($d['low'])<p class="mt-1 text-xs font-bold text-amber-700">{{ __('store.low_stock', ['n' => $d['low']]) }}</p>@endif
        <div class="mt-auto pt-3">
            @if ($d['out'])
                <button type="button" class="btn btn-sun w-full !px-2" data-stock-request data-product="{{ $p->id }}" data-variant="0" data-name="{{ $p->name }}">{{ __('store.request_now') }}</button>
            @elseif ($p->isVariable())
                <a href="{{ $url }}" class="btn btn-secondary w-full !px-2">{{ __('store.choose_options') }}</a>
            @else
                <button type="button" class="btn btn-primary w-full !px-2" data-add-to-cart data-product="{{ $p->id }}" data-variant="0">
                    <svg class="size-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h8.8a1 1 0 0 0 1-.8L20 8H6M9 20h.01M17 20h.01"/></svg>
                    <span class="truncate">{{ __('store.add_to_cart') }}</span>
                </button>
            @endif
        </div>
    </div>
</div>
