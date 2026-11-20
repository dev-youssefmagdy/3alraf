{{-- PageHeader (Figma PageHeader): breadcrumbs (collapse to a back link on mobile), title + count pill, subtitle, and the `actions` (default slot): primary action at the end side on desktop;
     on mobile it becomes a full-width 52px button under the title and the rest go in the slot as they are. `crumbs` = [[label, url]] (legacy name) or `breadcrumbs`. --}}
@props(['title', 'count' => null, 'crumbs' => [], 'breadcrumbs' => [], 'subtitle' => null])
@php($trail = $breadcrumbs ?: $crumbs)
<div {{ $attributes->merge(['class' => 'mb-5 flex flex-wrap items-end justify-between gap-x-4 gap-y-3 md:mb-6']) }} data-page-header>
    <div class="min-w-0">
        @if ($trail)<x-ui.breadcrumbs :items="$trail" class="mb-1" />@endif
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-bold text-panel-text md:text-panel-h1">{{ $title }}</h1>
            @if ($count !== null)<span class="rounded-full bg-panel-surface-sunken px-2.5 py-0.5 text-xs font-bold text-panel-text-muted">{{ $count }}</span>@endif
        </div>
        @if ($subtitle)<p class="mt-1 text-panel-body text-panel-text-muted">{{ $subtitle }}</p>@endif
    </div>
    @if (trim($slot))<div class="flex flex-wrap items-center gap-2 max-md:w-full max-md:[&>.btn-primary]:min-h-[52px] max-md:[&>.btn-primary]:w-full">{{ $slot }}</div>@endif
</div>
