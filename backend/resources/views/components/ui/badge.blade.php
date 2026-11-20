{{-- Badge (Figma Badge): tone neutral|success|warning|danger|info|accent, style soft|solid, dot, size md|sm. Always text (+ dot), never colour alone. --}}
@props(['tone' => 'neutral', 'style' => 'soft', 'dot' => false, 'size' => 'md'])
@php
    // legacy enum ->badge() classes (badge-warn ...) map to tones
    $tone = ['badge-success' => 'success', 'badge-danger' => 'danger', 'badge-warn' => 'warning', 'badge-info' => 'info', 'badge-muted' => 'neutral', 'badge-accent' => 'accent'][$tone] ?? $tone;
    $soft = ['neutral' => 'bg-panel-surface-sunken text-panel-text-muted', 'success' => 'bg-panel-success-bg text-panel-success-fg', 'warning' => 'bg-panel-warning-bg text-panel-warning-fg',
        'danger' => 'bg-panel-danger-bg text-panel-danger-fg', 'info' => 'bg-panel-info-bg text-panel-info-fg', 'accent' => 'bg-panel-accent-soft text-panel-accent-text'];
    $solid = ['neutral' => 'bg-panel-text text-panel-bg', 'success' => 'bg-panel-success-solid text-white', 'warning' => 'bg-panel-warning-solid text-panel-text',
        'danger' => 'bg-panel-danger-solid text-white', 'info' => 'bg-panel-info-solid text-white', 'accent' => 'bg-panel-accent text-panel-on-accent'];
    $c = ($style === 'solid' ? $solid : $soft)[$tone] ?? $soft['neutral'];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 whitespace-nowrap rounded-full font-semibold $c ".($size === 'sm' ? 'px-2 py-px text-[11px]/4' : 'px-2.5 py-0.5 text-xs/5')]) }}>@if ($dot)<span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>@endif{{ $slot }}</span>
