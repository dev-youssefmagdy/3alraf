{{-- Bundle card (design 8:527): the whole card is one link; gold-soft media (bundle image or a collage of its products). --}}
@php
    $bItems = array_slice(array_values(array_filter($b['components'] ?? [], fn ($c) => ! empty($c['image']))), 0, 4);
    $saves = bccomp((string) $b['savings'], '0', 2) > 0;
@endphp
<a href="{{ route('storefront.bundle.show', $b['slug']) }}" class="product-card group flex flex-col rounded-2xl border border-slate-200 bg-white p-2 shadow-card">
    <div class="relative aspect-square overflow-hidden rounded-xl bg-accent-light">
        @if ($b['image'])
            <img src="{{ $b['image'] }}" alt="{{ $b['name'] }}" class="product-card-img size-full object-cover {{ $b['in_stock'] ? '' : 'opacity-60 grayscale' }}" loading="lazy" decoding="async" width="400" height="400">
        @elseif (count($bItems) > 1)
            <div class="grid size-full grid-cols-2 gap-1.5 p-3 {{ $b['in_stock'] ? '' : 'opacity-60 grayscale' }}">
                @foreach ($bItems as $c)<img src="{{ $c['image'] }}" alt="" class="aspect-square size-full rounded-lg bg-white object-cover {{ count($bItems) === 3 && $loop->first ? 'col-span-2 aspect-auto' : '' }}" loading="lazy" decoding="async">@endforeach
            </div>
        @elseif ($bItems)
            <img src="{{ $bItems[0]['image'] }}" alt="" class="product-card-img size-full object-cover" loading="lazy" decoding="async">
        @endif
        <span class="badge badge-bundle absolute start-2 top-2">{{ __('store.bundle') }}</span>
    </div>
    <div class="flex flex-1 flex-col gap-1 px-1 pb-1 pt-2">
        @if (! empty($b['components']))<p class="text-xs font-semibold text-accent-dark">{{ __('store.bundle_items', ['n' => count($b['components'])]) }}</p>@endif
        <p class="line-clamp-2 min-h-11 text-sm/[22px] font-semibold text-slate-950 group-hover:text-brand-600">{{ $b['name'] }}</p>
        <p class="flex flex-wrap items-baseline gap-x-2">
            <span class="price text-lg/6 text-brand-600">{{ number_format((float) $b['price'], 2) }} <small class="font-sans text-xs font-bold">{{ setting('currency_symbol') }}</small></span>
            @if ($saves)<span class="price-old">{{ number_format((float) $b['regular_total'], 2) }}</span>@endif
        </p>
        <p class="mt-auto pt-1">
            @unless ($b['in_stock'])<span class="badge badge-out">{{ __('store.sold_out') }}</span>
            @elseif ($saves)<span class="badge bg-sun-400 text-slate-950">{{ __('store.bundle_save', ['amount' => number_format((float) $b['savings'], 2).' '.setting('currency_symbol')]) }}</span>@endunless
        </p>
    </div>
</a>
