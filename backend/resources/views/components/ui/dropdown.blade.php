{{-- Menu (Figma Panel/Dropdown menu) opened by a trigger slot; items are <a>/<button> in the default slot (class "ui-menu-item"). Uses ui.js data-dropdown. --}}
@props(['id', 'align' => 'end'])
<div class="relative inline-block">
    <button type="button" data-dropdown-toggle="{{ $id }}" aria-haspopup="menu" {{ $trigger->attributes->merge(['class' => 'btn-sm btn-secondary']) }}>{{ $trigger }}</button>
    <div id="{{ $id }}" data-dropdown role="menu" class="absolute {{ $align === 'end' ? 'end-0' : 'start-0' }} z-50 mt-2 hidden min-w-52 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-lg
        [&_.ui-menu-item]:flex [&_.ui-menu-item]:w-full [&_.ui-menu-item]:items-center [&_.ui-menu-item]:gap-2 [&_.ui-menu-item]:rounded-xl [&_.ui-menu-item]:px-3 [&_.ui-menu-item]:py-2 [&_.ui-menu-item]:text-start [&_.ui-menu-item]:text-sm [&_.ui-menu-item:hover]:bg-slate-100">
        {{ $slot }}
    </div>
</div>
