{{-- Chip (Figma Chip): filter chip (button / link) with selected state; removable adds an x. --}}
@props(['selected' => false, 'removable' => false, 'href' => null])
@php $cls = 'inline-flex min-h-9 items-center gap-1.5 whitespace-nowrap rounded-full border px-3.5 text-[13px] font-semibold transition '.($selected ? 'border-panel-accent bg-panel-accent-soft text-panel-accent-text' : 'border-panel-border-strong bg-panel-surface text-panel-text hover:border-panel-accent'); @endphp
@if ($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => $cls]) }} @if ($selected) aria-current="true" @endif>{{ $slot }}</a>
@else
<button type="button" {{ $attributes->merge(['class' => $cls]) }} aria-pressed="{{ $selected ? 'true' : 'false' }}">{{ $slot }}@if ($removable)<x-ui.icon name="x" class="size-3.5" />@endif</button>
@endif
