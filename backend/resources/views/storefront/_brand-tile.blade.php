{{-- Brand tile (design 8:546): logo on a tinted panel (initial in a white circle without a logo), name and sellable_count. --}}
@php $tint = ['bg-pop-50', 'bg-sun-100', 'bg-mint-100', 'bg-violet-100', 'bg-brand-50'][($i ?? $br->id) % 5]; @endphp
<a href="{{ route('storefront.brand.show', $br->slug) }}" class="product-card group flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-2 shadow-card">
    <span class="grid aspect-[4/3] place-items-center overflow-hidden rounded-xl {{ $tint }} p-4">
        @if ($br->logo_url)<img src="{{ $br->logo_url }}" alt="{{ $br->name }}" class="product-card-img max-h-full max-w-full object-contain" loading="lazy" decoding="async">
        @else<span class="grid size-14 place-items-center rounded-full bg-white font-display text-2xl font-extrabold text-brand-600 shadow-card lg:size-16 lg:text-3xl" aria-hidden="true">{{ mb_strtoupper(mb_substr($br->name, 0, 1)) }}</span>@endif
    </span>
    <span class="px-1 pb-1 pt-2 text-center">
        <span class="block truncate text-sm font-bold text-slate-950 group-hover:text-brand-600">{{ $br->name }}</span>
        <span class="block text-xs/[18px] text-slate-600">{{ $br->sellable_count }} {{ __('store.brand_products') }}</span>
    </span>
</a>
