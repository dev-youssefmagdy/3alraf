{{-- Breadcrumbs (Figma Breadcrumbs): items = [[label, url|null]]; desktop shows the trail, mobile collapses to a back chevron + current page. --}}
@props(['items' => []])
@if (count($items))
@php($items = array_values($items))
@php($parent = count($items) > 1 ? $items[count($items) - 2] : null)
<nav {{ $attributes->merge(['class' => 'text-xs text-panel-text-subtle']) }} aria-label="{{ __('ui.breadcrumb') }}">
    @if ($parent && ($parent[1] ?? null))
        <a href="{{ $parent[1] }}" class="inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-panel-text-muted md:hidden"><x-ui.icon name="back" class="size-4" />{{ $parent[0] }}</a>
    @endif
    <ol class="hidden flex-wrap items-center gap-1.5 md:flex">
        @foreach ($items as $i => $c)
            <li class="flex items-center gap-1.5">
                @if ($i > 0)<x-ui.icon name="chevron-end" class="size-3.5" />@endif
                @if ($loop->last || empty($c[1]))<span class="font-semibold text-panel-text" aria-current="page">{{ $c[0] }}</span>
                @else<a href="{{ $c[1] }}" class="hover:text-panel-accent-text">{{ $c[0] }}</a>@endif
            </li>
        @endforeach
    </ol>
</nav>
@endif
