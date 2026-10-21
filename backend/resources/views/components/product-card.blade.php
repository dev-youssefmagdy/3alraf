@props(['tp'])
@php
    $p = $tp->product;
    $d = $tp->display ?? app(\App\Services\Storefront\CardPresenter::class)->decorate(collect([$tp]))->first()->display;
    $url = route('storefront.product.show', ['locale' => app()->getLocale(), 'slug' => $p->slug]);
@endphp
<div {{ $attributes->merge(['class' => 'group relative flex flex-col rounded-xl border border-slate-200 bg-white p-3 transition hover:shadow-lg']) }} data-product-card>
    @if ($d['percent'])<span class="absolute start-3 top-3 z-10 rounded-full bg-brand-600 px-2 py-0.5 text-[11px] font-bold text-white">{{ __('store.off', ['p' => $d['percent']]) }}</span>@endif
    <a href="{{ $url }}" class="block overflow-hidden rounded-lg">
        @if ($img = $p->primaryImageUrl())<img src="{{ $img }}" alt="{{ $p->name }}" loading="lazy" class="aspect-square w-full object-cover transition group-hover:scale-105">
        @else<div class="aspect-square w-full bg-slate-100"></div>@endif
    </a>
    <a href="{{ $url }}" class="mt-2 line-clamp-2 min-h-10 text-sm font-semibold text-slate-800 hover:text-brand-600">{{ $p->name }}</a>
    @if ($d['rating'])<p class="mt-1 text-xs text-amber-500">{{ str_repeat('★', (int) round($d['rating'])) }}<span class="text-slate-300">{{ str_repeat('★', 5 - (int) round($d['rating'])) }}</span> <span class="text-slate-400">({{ $d['reviews'] }})</span></p>@endif
    <div class="mt-1 flex flex-wrap items-baseline gap-x-2">
        <span class="text-base font-bold {{ $d['flash'] ? 'text-brand-600' : 'text-slate-900' }}">@if ($d['from'] ?? false)<small class="text-xs font-medium text-slate-500">{{ __('store.from_price') }}</small> @endif{{ number_format((float) $d['price'], 2) }} <small class="text-xs font-medium">{{ current_tenant()->currency_symbol }}</small></span>
        @if ($d['old'])<span class="text-xs text-slate-400 line-through">{{ number_format((float) $d['old'], 2) }}</span>@endif
    </div>
    @if ($d['flash'] && $d['ends_at'])<p class="mt-1 text-[11px] text-brand-600">{{ __('store.ends_in') }} <span data-countdown="{{ $d['ends_at'] }}" class="font-mono font-bold"></span></p>@endif
    <div class="mt-auto pt-3">
        @if ($p->isVariable())
            <a href="{{ $url }}" class="btn btn-secondary w-full !py-1.5">{{ __('store.choose_options') }}</a>
        @else
            <button type="button" class="btn btn-primary w-full !py-1.5" data-add-to-cart data-product="{{ $p->id }}" data-variant="0">{{ __('store.add_to_cart') }}</button>
        @endif
    </div>
</div>
