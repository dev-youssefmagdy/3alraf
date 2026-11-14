@props(['title', 'href' => null, 'tag' => 'h2', 'arrows' => true, 'titleClass' => 'section-title'])
{{-- Rail header (design 19:639): display-font title, "view all" with a chevron in the reading direction, and the Swiper prev/next (≥1024 only, see sliders.js). Put it inside the [data-swiper] root. --}}
<div {{ $attributes->merge(['class' => 'mb-4 flex items-center justify-between gap-3']) }}>
    <{{ $tag }} class="{{ $titleClass }} min-w-0">{{ $title }}</{{ $tag }}>
    <div class="flex shrink-0 items-center gap-2">
        @if ($href)
            <a href="{{ $href }}" class="inline-flex min-h-11 items-center gap-1 text-sm font-bold text-brand-600 hover:text-brand-700">{{ __('store.view_all') }}<svg class="size-4 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></a>
        @endif
        @if ($arrows)<x-slider-arrows />@endif
    </div>
</div>
