{{-- Tooltip (Figma Tooltip): hint only, never essential info. Wrap the trigger; shows on hover and keyboard focus. placement top|bottom. --}}
@props(['text', 'placement' => 'top'])
@php $id = 'tt'.substr(md5($text.$placement.random_int(0, 9999)), 0, 6); @endphp
<span {{ $attributes->merge(['class' => 'group/tt relative inline-flex']) }} aria-describedby="{{ $id }}">
    {{ $slot }}
    <span id="{{ $id }}" role="tooltip" class="pointer-events-none absolute start-1/2 z-50 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-panel-text px-2.5 py-1 text-xs font-medium text-panel-bg shadow-panel-md rtl:translate-x-1/2 group-hover/tt:block group-focus-within/tt:block {{ $placement === 'bottom' ? 'top-full mt-1.5' : 'bottom-full mb-1.5' }}">{{ $text }}</span>
</span>
