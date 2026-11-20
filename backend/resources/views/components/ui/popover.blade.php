{{-- Popover (Figma Popover): trigger slot + content slot, for rich help or an inline confirm (max 2 actions). Uses the data-dropdown toggle from ui.js. --}}
@props(['id', 'align' => 'end'])
<div class="relative inline-block">
    <button type="button" data-dropdown-toggle="{{ $id }}" aria-haspopup="dialog" {{ $trigger->attributes->merge(['class' => 'inline-flex']) }}>{{ $trigger }}</button>
    <div id="{{ $id }}" data-dropdown role="dialog" {{ $attributes->merge(['class' => 'absolute z-50 mt-2 hidden w-72 rounded-2xl border border-panel-border bg-panel-surface-raised p-4 text-sm text-panel-text shadow-panel-md '.($align === 'end' ? 'end-0' : 'start-0')]) }}>{{ $slot }}</div>
</div>
