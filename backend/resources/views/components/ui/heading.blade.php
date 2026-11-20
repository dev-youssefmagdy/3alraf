{{-- Heading (Figma Heading): level 1|2|3 on the panel type scale, optional eyebrow (overline) and subtitle slot. --}}
@props(['level' => 1, 'eyebrow' => null])
@php
    $tag = 'h'.$level;
    $cls = [1 => 'text-panel-h1 font-bold', 2 => 'text-panel-h2 font-bold', 3 => 'text-panel-h3 font-semibold'][$level] ?? 'text-panel-h3 font-semibold';
@endphp
<div {{ $attributes->only('class') }}>
    @if ($eyebrow)<p class="mb-1 text-panel-overline font-bold uppercase tracking-wide text-panel-accent-text">{{ $eyebrow }}</p>@endif
    <{{ $tag }} class="{{ $cls }} text-panel-text">{{ $slot }}</{{ $tag }}>
    @isset($subtitle)<p class="mt-1 text-panel-body text-panel-text-muted">{{ $subtitle }}</p>@endisset
</div>
