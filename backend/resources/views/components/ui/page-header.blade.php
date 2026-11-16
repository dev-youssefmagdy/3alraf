{{-- Page header (Figma Panel/Page header): breadcrumbs, title + count pill, actions slot (primary last). Use in a layout's `header` slot. --}}
@props(['title', 'count' => null, 'crumbs' => [], 'subtitle' => null])
<div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-end justify-between gap-4']) }} data-page-header>
    <div class="min-w-0">
        @if ($crumbs)
            <nav class="mb-1 flex flex-wrap items-center gap-1 text-xs text-slate-500" aria-label="breadcrumb">
                @foreach ($crumbs as $i => [$label, $href])
                    @if ($i)<svg class="size-3 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>@endif
                    @if ($href)<a href="{{ $href }}" class="hover:text-panel-text">{{ $label }}</a>@else<span class="text-slate-700">{{ $label }}</span>@endif
                @endforeach
            </nav>
        @endif
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold text-slate-950">{{ $title }}</h1>
            @if ($count !== null)<span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-600">{{ $count }}</span>@endif
        </div>
        @if ($subtitle)<p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>@endif
    </div>
    @if (trim($slot))<div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>@endif
</div>
