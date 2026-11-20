{{-- StatCard (Figma StatCard): icon tile, delta chip (trend up|down|flat), label, number first, hint, sparkline last. size default | compact (mobile 2-up).
     Legacy `tone` (brand|mint|sun|violet|emerald|slate|panel) now only tints the icon tile. --}}
@props(['label', 'value', 'hint' => null, 'tone' => 'panel', 'icon' => null, 'trend' => null, 'delta' => null, 'spark' => null, 'size' => 'default', 'href' => null])
@php
    $tile = ['panel' => 'bg-panel-accent-soft text-panel-accent-text', 'brand' => 'bg-panel-accent-soft text-panel-accent-text', 'violet' => 'bg-panel-info-bg text-panel-info-fg',
        'mint' => 'bg-panel-success-bg text-panel-success-fg', 'emerald' => 'bg-panel-success-bg text-panel-success-fg', 'sun' => 'bg-panel-warning-bg text-panel-warning-fg',
        'slate' => 'bg-panel-surface-sunken text-panel-text-muted'][$tone] ?? 'bg-panel-accent-soft text-panel-accent-text';
    $chip = ['up' => ['success', 'arrow-up'], 'down' => ['danger', 'arrow-down'], 'flat' => ['neutral', 'minus']][$trend] ?? null;
    $compact = $size === 'compact';
@endphp
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'flex min-w-0 flex-col rounded-2xl border border-panel-border bg-panel-surface shadow-panel-xs '.($compact ? 'p-4' : 'p-4 md:p-5').($href ? ' transition hover:border-panel-accent hover:shadow-panel-md' : '')]) }}>
    <div class="flex items-start justify-between gap-2">
        @if ($icon)<span class="grid shrink-0 place-items-center rounded-xl {{ $tile }} {{ $compact ? 'size-8' : 'size-9 md:size-10' }}"><x-ui.icon :name="$icon" class="size-5" /></span>@else<span></span>@endif
        @if ($chip && $delta !== null)<x-ui.badge :tone="$chip[0]" size="sm"><x-ui.icon :name="$chip[1]" class="size-3" /><bdi dir="ltr">{{ $delta }}</bdi></x-ui.badge>@endif
    </div>
    <p class="mt-3 text-panel-small font-medium text-panel-text-muted {{ $compact ? '' : 'md:mt-4' }}">{{ $label }}</p>
    <p class="mt-0.5 font-bold tabular-nums text-panel-text [overflow-wrap:anywhere] {{ $compact ? 'text-panel-num' : 'text-panel-num md:text-panel-stat' }}">{{ $value }}</p>
    @if ($hint || $spark)
        <div class="mt-2 flex items-end justify-between gap-2">
            <p class="min-w-0 text-xs text-panel-text-subtle">{{ $hint }}</p>
            @if ($spark)<x-ui.sparkline :points="$spark" :trend="$trend ?? 'up'" class="hidden sm:block" />@endif
        </div>
    @endif
</{{ $tag }}>
