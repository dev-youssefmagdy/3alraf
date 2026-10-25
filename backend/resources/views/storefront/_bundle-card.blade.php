<a href="{{ route('storefront.bundle.show', $b['slug']) }}" class="group block overflow-hidden rounded-2xl bg-white ring-1 ring-slate-100 transition hover:shadow-md">
    <div class="aspect-square bg-slate-50">@if ($b['image'])<img src="{{ $b['image'] }}" alt="{{ $b['name'] }}" class="size-full object-cover" loading="lazy">@elseif (! empty($b['components'][0]['image']))<img src="{{ $b['components'][0]['image'] }}" alt="" class="size-full object-cover" loading="lazy">@endif</div>
    <div class="space-y-1 p-3">
        <span class="inline-block rounded bg-brand-50 px-2 py-0.5 text-xs font-bold text-brand-600">{{ __('store.bundle') }}</span>
        <p class="line-clamp-2 font-semibold">{{ $b['name'] }}</p>
        <p><b class="text-brand-600">{{ number_format((float) $b['price'], 2) }}</b> <small>{{ current_tenant()->currency_symbol }}</small>
            @if (bccomp($b['savings'], '0', 2) > 0)<span class="ms-1 text-xs text-slate-400 line-through">{{ number_format((float) $b['regular_total'], 2) }}</span>@endif</p>
        @unless ($b['in_stock'])<p class="text-xs font-semibold text-red-600">{{ __('store.sold_out') }}</p>@endunless
    </div>
</a>
