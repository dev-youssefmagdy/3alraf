@props(['type' => 'search', 'title' => null, 'text' => null, 'href' => null, 'action' => null])
{{-- Empty state (design 21:1787): tinted blob + 52px icon, H2, one line, one CTA. Types: cart, search, wishlist, orders, gift. --}}
@php
    $kinds = [
        'cart' => ['bg-pop-50 text-pop-600', 'M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h8.8a1 1 0 0 0 1-.8L20 8H6M9 20h.01M17 20h.01'],
        'search' => ['bg-violet-100 text-violet-700', 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5'],
        'wishlist' => ['bg-pop-50 text-pop-600', 'M12 20s-7-4.4-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.6-9 9-9 9z'],
        'orders' => ['bg-mint-100 text-mint-700', 'M21 8 12 3 3 8v8l9 5 9-5zM3 8l9 5 9-5M12 13v8'],
        'gift' => ['bg-sun-100 text-accent-dark', 'M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z'],
    ];
    [$tone, $path] = $kinds[$type] ?? $kinds['search'];
@endphp
<div {{ $attributes->merge(['class' => 'col-span-full flex flex-col items-center px-4 py-10 text-center']) }}>
    <span class="grid size-[120px] place-items-center rounded-[42%_58%_55%_45%/48%_42%_58%_52%] {{ $tone }}" aria-hidden="true">
        <svg class="size-[52px]" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $path }}"/></svg>
    </span>
    <h2 class="mt-5 font-display text-[22px]/[30px] font-bold text-slate-950">{{ $title ?? __('store.empty_title') }}</h2>
    @if ($text)<p class="mt-1 max-w-sm text-sm/[22px] text-slate-600">{{ $text }}</p>@endif
    {{ $slot }}
    @if ($href && $action)<a href="{{ $href }}" class="btn btn-primary mt-5">{{ $action }}</a>@endif
</div>
