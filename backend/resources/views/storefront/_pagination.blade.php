@if ($paginator->hasPages())
@php($arrow = fn ($d) => '<svg class="size-5 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="'.$d.'"/></svg>')
<nav class="flex flex-wrap items-center justify-center gap-1.5 text-sm font-bold" aria-label="{{ __('store.pagination') }}">
    @if ($paginator->onFirstPage())<span class="grid size-11 place-items-center rounded-full border border-slate-200 bg-white text-slate-300" aria-hidden="true">{!! $arrow('m15 6-6 6 6 6') !!}</span>
    @else<a href="{{ $paginator->previousPageUrl() }}" data-page-link rel="prev" class="grid size-11 place-items-center rounded-full border border-slate-200 bg-white text-slate-950 hover:border-brand-600 hover:text-brand-600" aria-label="{{ __('store.prev') }}">{!! $arrow('m15 6-6 6 6 6') !!}</a>@endif
    @foreach ($elements as $element)
        @if (is_string($element))<span class="px-1 text-slate-500">{{ $element }}</span>@endif
        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())<span class="grid size-11 place-items-center rounded-full bg-brand-600 text-white" aria-current="page">{{ $page }}</span>
                @else<a href="{{ $url }}" data-page-link class="grid size-11 place-items-center rounded-full border border-slate-200 bg-white text-slate-950 hover:border-brand-600 hover:text-brand-600">{{ $page }}</a>@endif
            @endforeach
        @endif
    @endforeach
    @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" data-page-link rel="next" class="grid size-11 place-items-center rounded-full border border-slate-200 bg-white text-slate-950 hover:border-brand-600 hover:text-brand-600" aria-label="{{ __('store.next') }}">{!! $arrow('m9 6 6 6-6 6') !!}</a>
    @else<span class="grid size-11 place-items-center rounded-full border border-slate-200 bg-white text-slate-300" aria-hidden="true">{!! $arrow('m9 6 6 6-6 6') !!}</span>@endif
</nav>
@endif
