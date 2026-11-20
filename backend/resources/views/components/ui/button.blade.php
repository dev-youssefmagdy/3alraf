{{-- Button (Figma Button): variant primary|secondary|ghost|danger|sun, size sm|md|lg, optional icon (start|end), loading state. href renders <a>. --}}
@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'loading' => false, 'icon' => null, 'iconPosition' => 'start', 'type' => 'button'])
@php
    $cls = 'btn btn-'.$variant.' btn-'.$size.($loading ? ' btn-loading' : '');
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $cls]) }} @if ($loading) aria-busy="true" @endif>@if ($icon && $iconPosition === 'start')<x-ui.icon :name="$icon" class="size-[18px]" />@endif{{ $slot }}@if ($icon && $iconPosition === 'end')<x-ui.icon :name="$icon" class="size-[18px]" />@endif</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $cls]) }} @disabled($loading) @if ($loading) aria-busy="true" @endif>@if ($icon && $iconPosition === 'start')<x-ui.icon :name="$icon" class="size-[18px]" />@endif{{ $slot }}@if ($icon && $iconPosition === 'end')<x-ui.icon :name="$icon" class="size-[18px]" />@endif</button>
@endif
