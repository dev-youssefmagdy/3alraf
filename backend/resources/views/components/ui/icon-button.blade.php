{{-- IconButton (Figma IconButton): icon + required aria label. variant ghost|soft|filled|outline, size sm (36, desktop only)|md (44), optional count badge. --}}
@props(['icon', 'label', 'variant' => 'ghost', 'size' => 'md', 'badge' => null, 'href' => null, 'type' => 'button'])
@php $cls = 'ibtn'.($variant !== 'ghost' ? ' ibtn-'.$variant : '').($size === 'sm' ? ' ibtn-sm' : ''); @endphp
@if ($href)
<a href="{{ $href }}" aria-label="{{ $label }}" title="{{ $label }}" {{ $attributes->merge(['class' => $cls]) }}>
@else
<button type="{{ $type }}" aria-label="{{ $label }}" title="{{ $label }}" {{ $attributes->merge(['class' => $cls]) }}>
@endif
    <x-ui.icon :name="$icon" />
    @if ($badge !== null && $badge !== 0 && $badge !== '')<span class="absolute -end-0.5 -top-0.5 min-w-4 rounded-full bg-panel-danger-solid px-1 text-center text-[10px] font-bold leading-4 text-white">{{ $badge }}</span>@endif
@if ($href)</a>@else</button>@endif
