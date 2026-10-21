@if ($paginator->hasPages())
<nav class="flex flex-wrap items-center justify-center gap-1 text-sm" aria-label="pagination">
    @foreach ($elements as $element)
        @if (is_string($element))<span class="px-2 text-slate-400">{{ $element }}</span>@endif
        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())<span class="grid size-9 place-items-center rounded-lg bg-brand-600 font-bold text-white">{{ $page }}</span>
                @else<a href="{{ $url }}" data-page-link class="grid size-9 place-items-center rounded-lg border border-slate-200 bg-white hover:border-brand-600 hover:text-brand-600">{{ $page }}</a>@endif
            @endforeach
        @endif
    @endforeach
</nav>
@endif
