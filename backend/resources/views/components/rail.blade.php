@props(['title', 'items', 'href' => null, 'countdown' => null, 'accent' => false])
{{--
    Product rail (design 19:1272 / rail header 19:639) on Swiper: preset "rail", or "flash" with a countdown (pop block, full-bleed on phones).
    2.3 / 3.3 / 4 / 5 cards per view; arrows from 1024px, progress bar below. See resources/js/lib/sliders.js for the markup contract.
--}}
@if ($items->isNotEmpty())
@php
    $flash = (bool) $countdown;
    $prev = '<svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>';
    $next = '<svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>';
@endphp
<section data-swiper="{{ $flash ? 'flash' : 'rail' }}" data-swiper-bleed @if ($flash) id="flash-sale" @endif
    {{ $attributes->merge(['class' => 'mt-8 lg:mt-12 '.($flash ? 'max-lg:-mx-4 bg-pop-600 px-4 py-6 text-white lg:rounded-[32px] lg:p-8' : ($accent ? 'max-lg:-mx-4 bg-brand-50 px-4 py-6 lg:rounded-[32px] lg:p-8' : ''))]) }}>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
        <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2">
            @if ($flash)<span class="grid size-10 shrink-0 place-items-center rounded-full bg-sun-400 text-slate-950" aria-hidden="true"><svg class="size-5" fill="currentColor" viewBox="0 0 24 24"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg></span>@endif
            <h2 class="font-display text-[22px]/[30px] font-bold lg:text-[28px]/9 {{ $flash ? 'text-white' : 'text-slate-950' }}">{{ $title }}</h2>
            @if ($countdown)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-sun-400 px-3 py-1 text-sm font-bold text-slate-950">{{ __('store.ends_in') }} <span data-countdown="{{ $countdown }}" class="tabular-nums" aria-live="off"></span></span>
            @endif
        </div>
        <div class="flex items-center gap-2">
            @if ($href)
                <a href="{{ $href }}" class="inline-flex min-h-11 items-center gap-1 text-sm font-bold {{ $flash ? 'text-sun-300 hover:text-white' : 'text-brand-600 hover:text-brand-700' }}">{{ __('store.view_all') }}<svg class="size-4 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></a>
            @endif
            <button type="button" data-swiper-prev class="icon-btn icon-btn-outline" aria-label="{{ __('store.prev') }}">{!! $prev !!}</button>
            <button type="button" data-swiper-next class="icon-btn icon-btn-outline" aria-label="{{ __('store.next') }}">{!! $next !!}</button>
        </div>
    </div>
    <div class="swiper !py-1 max-lg:-mx-4">
        <div class="swiper-wrapper">
            @foreach ($items as $item)<div class="swiper-slide"><x-product-card :product="$item" class="h-full" /></div>@endforeach
        </div>
    </div>
    <div data-swiper-pagination class="mx-auto mt-4 w-32 {{ $flash ? '!bg-white/30 [&_.swiper-pagination-progressbar-fill]:!bg-sun-400' : '' }}"></div>
</section>
@endif
