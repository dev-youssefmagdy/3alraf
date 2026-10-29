@props(['title', 'items', 'href' => null, 'countdown' => null, 'accent' => false])
@if ($items->isNotEmpty())
<section {{ $attributes->merge(['class' => 'mt-8 rounded-2xl p-4 '.($accent ? 'bg-brand-50 ring-1 ring-brand-100' : 'bg-white ring-1 ring-slate-100')]) }}>
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-lg font-extrabold text-slate-900">{{ $title }}</h2>
            @if ($countdown)<span class="rounded-lg bg-brand-600 px-3 py-1 text-sm text-white">{{ __('store.ends_in') }} <span data-countdown="{{ $countdown }}" class="font-mono font-bold"></span></span>@endif
        </div>
        @if ($href)<a href="{{ $href }}" class="text-sm font-semibold text-brand-600 hover:underline">{{ __('store.view_all') }}</a>@endif
    </div>
    <div data-scroll-row class="-mx-1 flex snap-x gap-3 overflow-x-auto px-1 pb-2">
        @foreach ($items as $tp)<div class="w-44 shrink-0 snap-start sm:w-52"><x-product-card :tp="$tp" class="h-full" /></div>@endforeach
    </div>
</section>
@endif
