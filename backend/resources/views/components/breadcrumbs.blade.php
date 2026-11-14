@props(['items' => []])
{{-- Breadcrumbs (design 7:315). $items = [[label, url], …] (the same array pages pass to the layout's :breadcrumbs for JSON-LD); the last one is the current page. --}}
@if (count($items))
<nav {{ $attributes->merge(['class' => 'mb-4 -mx-4 overflow-x-auto px-4 [scrollbar-width:none] lg:mx-0 lg:px-0']) }} aria-label="{{ __('store.breadcrumb') }}">
    <ol class="flex w-max items-center gap-1.5 text-xs/[18px] text-slate-600">
        @foreach (array_values($items) as $i => [$label, $url])
            <li class="flex items-center gap-1.5">
                @if ($i > 0)<svg class="size-3.5 shrink-0 text-slate-400 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>@endif
                @if ($loop->last)<span class="max-w-[60vw] truncate font-semibold text-slate-950" aria-current="page">{{ $label }}</span>
                @else<a href="{{ $url }}" class="inline-flex min-h-8 items-center whitespace-nowrap hover:text-brand-600">{{ $label }}</a>@endif
            </li>
        @endforeach
    </ol>
</nav>
@endif
