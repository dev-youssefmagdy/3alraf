{{-- DropdownMenu (Figma DropdownMenu): trigger slot + items (<a>/<button> with class "ui-menu-item"; add "danger" for the last, destructive item after a divider). Uses ui.js data-dropdown. --}}
@props(['id', 'align' => 'end', 'triggerClass' => 'btn btn-secondary btn-sm'])
<div class="relative inline-block">
    <button type="button" data-dropdown-toggle="{{ $id }}" aria-haspopup="menu" aria-expanded="false" {{ $trigger->attributes->merge(['class' => $triggerClass]) }}>{{ $trigger }}</button>
    <div id="{{ $id }}" data-dropdown role="menu" class="absolute {{ $align === 'end' ? 'end-0' : 'start-0' }} z-50 mt-2 hidden min-w-52 rounded-2xl border border-panel-border bg-panel-surface-raised p-1.5 shadow-panel-lg">{{ $slot }}</div>
</div>
