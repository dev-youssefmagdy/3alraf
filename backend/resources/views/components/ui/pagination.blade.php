{{-- Pagination (Figma Pagination) for a Laravel paginator: range text + numbered pages on desktop, previous / next with "page x of y" on mobile. --}}
@props(['paginator'])
@if ($paginator->hasPages())
@php($cur = $paginator->currentPage())
@php($last = $paginator->lastPage())
<nav {{ $attributes->merge(['class' => 'mt-4 flex flex-wrap items-center justify-between gap-3']) }} aria-label="{{ __('ui.pagination') }}">
    <p class="hidden text-sm text-panel-text-muted md:block">{{ __('ui.range', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}</p>
    <p class="text-sm text-panel-text-muted md:hidden">{{ __('ui.page_of', ['page' => $cur, 'pages' => $last]) }}</p>
    <div class="flex items-center gap-1">
        <a @if ($paginator->onFirstPage()) aria-disabled="true" @else href="{{ $paginator->previousPageUrl() }}" @endif class="ibtn ibtn-sm {{ $paginator->onFirstPage() ? 'opacity-40' : '' }}" aria-label="{{ __('ui.prev') }}"><x-ui.icon name="chevron-start" /></a>
        @foreach (collect(range(max(1, $cur - 2), min($last, $cur + 2))) as $p)
            <a href="{{ $paginator->url($p) }}" class="hidden size-9 place-items-center rounded-lg text-sm font-semibold md:grid {{ $p === $cur ? 'bg-panel-accent text-panel-on-accent' : 'text-panel-text-muted hover:bg-panel-surface-sunken' }}" @if ($p === $cur) aria-current="page" @endif>{{ $p }}</a>
        @endforeach
        <a @if (! $paginator->hasMorePages()) aria-disabled="true" @else href="{{ $paginator->nextPageUrl() }}" @endif class="ibtn ibtn-sm {{ ! $paginator->hasMorePages() ? 'opacity-40' : '' }}" aria-label="{{ __('ui.next') }}"><x-ui.icon name="chevron-end" /></a>
    </div>
</nav>
@endif
