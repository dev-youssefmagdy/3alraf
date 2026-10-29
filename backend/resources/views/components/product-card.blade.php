@props(['tp', 'eager' => false])
@php
    $p = $tp->product;
    $d = $tp->display ?? app(\App\Services\Storefront\CardPresenter::class)->decorate(collect([$tp]))->first()->display;
    $url = route('storefront.product.show', ['locale' => app()->getLocale(), 'slug' => $p->slug]);
    static $wished = null;
    $customer = auth('web')->user();
    $wished ??= $customer ? \App\Models\WishlistItem::where('customer_id', $customer->id)->pluck('product_id')->all() : [];
    $fav = in_array($p->id, $wished, true);
@endphp
<div {{ $attributes->merge(['class' => 'group relative flex flex-col rounded-xl border border-slate-200 bg-white p-3 transition hover:shadow-lg']) }} data-product-card>
    @if ($d['percent'])<span class="absolute right-5 top-5 z-10 rounded-full bg-brand-600 px-2 py-0.5 text-[11px] font-bold text-white">{{ __('store.off', ['p' => $d['percent']]) }}</span>@endif
    @if ($d['out'])<span class="absolute right-5 top-12 z-10 rounded-full bg-slate-800 px-2 py-0.5 text-[11px] font-bold text-white">{{ __('store.out_of_stock') }}</span>@endif
    <button type="button" data-card-wish="{{ $customer ? route('storefront.wishlist.toggle', ['locale' => app()->getLocale(), 'product' => $p->id]) : '' }}" data-login="{{ route('storefront.login', ['locale' => app()->getLocale()]) }}" aria-pressed="{{ $fav ? 'true' : 'false' }}" aria-label="{{ __('store.wishlist') }}" class="group/fav absolute left-5 top-5 z-10 grid size-8 place-items-center rounded-full bg-white/90 shadow ring-1 ring-slate-200 transition hover:scale-110 {{ $fav ? 'text-red-500' : 'text-slate-400' }}">
        <svg viewBox="0 0 24 24" class="size-4" fill="{{ $fav ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-4.6-9.3-9.1C1.2 8.8 3 5 6.6 5c2 0 3.4 1.1 4.2 2.4h.4C12 6.1 13.4 5 15.4 5 19 5 20.8 8.8 21.3 11.9 19 16.400 12 21 12 21z"/></svg>
    </button>
    <a href="{{ $url }}" class="block overflow-hidden rounded-lg">
        @if ($img = $p->primaryImageUrl())<x-media-img :src="$img" :alt="$p->name" width="400" height="400" :eager="(bool) $eager" class="aspect-square w-full object-cover transition group-hover:scale-105 {{ $d['out'] ? 'opacity-50 grayscale' : '' }}" />
        @else<div class="aspect-square w-full bg-slate-100"></div>@endif
    </a>
    <a href="{{ $url }}" class="mt-2 line-clamp-2 min-h-10 text-sm font-semibold text-slate-800 hover:text-brand-600">{{ $p->name }}</a>
    @if ($d['rating'])<p class="mt-1 text-xs text-amber-500">{{ str_repeat('★', (int) round($d['rating'])) }}<span class="text-slate-300">{{ str_repeat('★', 5 - (int) round($d['rating'])) }}</span> <span class="text-slate-400">({{ $d['reviews'] }})</span></p>@endif
    <div class="mt-1 flex flex-wrap items-baseline gap-x-2">
        <span class="text-base font-bold {{ $d['flash'] ? 'text-brand-600' : 'text-slate-900' }}">@if ($d['from'] ?? false)<small class="text-xs font-medium text-slate-500">{{ __('store.from_price') }}</small> @endif{{ number_format((float) $d['price'], 2) }} <small class="text-xs font-medium">{{ current_tenant()->currency_symbol }}</small></span>
        @if ($d['old'])<span class="text-xs text-slate-400 line-through">{{ number_format((float) $d['old'], 2) }}</span>@endif
    </div>
    @if ($d['flash'] && $d['ends_at'])<p class="mt-1 text-[11px] text-brand-600">{{ __('store.ends_in') }} <span data-countdown="{{ $d['ends_at'] }}" class="font-mono font-bold"></span></p>@endif
    @if ($d['low'])<p class="mt-1 text-xs font-semibold text-amber-600">{{ __('store.low_stock', ['n' => $d['low']]) }}</p>@endif
    <div class="mt-auto pt-3">
        @if ($d['out'])
            <button type="button" class="btn btn-secondary w-full !py-1.5" data-stock-request data-product="{{ $p->id }}" data-variant="0" data-name="{{ $p->name }}">{{ __('store.request_now') }}</button>
        @elseif ($p->isVariable())
            <a href="{{ $url }}" class="btn btn-secondary w-full !py-1.5">{{ __('store.choose_options') }}</a>
        @else
            <button type="button" class="btn btn-primary w-full !py-1.5" data-add-to-cart data-product="{{ $p->id }}" data-variant="0">{{ __('store.add_to_cart') }}</button>
        @endif
    </div>
</div>
