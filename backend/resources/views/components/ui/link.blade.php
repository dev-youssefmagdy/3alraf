{{-- Link (Figma Link): style inline (underlined, for body copy) | standalone (accent, bold, with arrow) | subtle (muted). external opens a new tab. --}}
@props(['href', 'style' => 'inline', 'external' => false])
@php
    $cls = ['inline' => 'font-semibold text-panel-accent-text underline underline-offset-2 hover:text-panel-accent-hover',
        'standalone' => 'inline-flex min-h-9 items-center gap-1 font-bold text-panel-accent-text hover:text-panel-accent-hover',
        'subtle' => 'text-panel-text-muted hover:text-panel-text'][$style] ?? '';
@endphp
<a href="{{ $href }}" {{ $attributes->merge(['class' => $cls.' rounded-sm text-sm transition']) }} @if ($external) target="_blank" rel="noopener noreferrer" @endif>{{ $slot }}@if ($external)<x-ui.icon name="external" class="ms-1 inline size-3.5" />@elseif ($style === 'standalone')<x-ui.icon name="chevron-end" class="size-4" />@endif</a>
