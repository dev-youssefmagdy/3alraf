{{-- CommandPalette (Figma CommandPalette): Ctrl/Cmd+K dialog that jumps to any page of the panel (groups = the sidebar data) and offers quick actions. groups = [['label', 'items' => [['label','href','icon']]]].
     Opened by [data-cmdk-open]; behaviour in resources/js/lib/ui.js. Full-screen sheet on phones. --}}
@props(['groups' => [], 'placeholder' => null, 'shortcut' => 'K'])
<div id="cmdk" data-cmdk class="fixed inset-0 z-[70] hidden items-start justify-center md:p-4 md:pt-[12vh]" aria-hidden="true">
    <div data-cmdk-close class="absolute inset-0 bg-panel-overlay/50 backdrop-blur-[2px]"></div>
    <div role="dialog" aria-modal="true" aria-label="{{ $placeholder ?? __('admin.search') }}" class="relative flex h-full w-full flex-col overflow-hidden bg-panel-surface shadow-panel-overlay md:h-auto md:max-h-[70vh] md:max-w-xl md:rounded-3xl">
        <div class="flex items-center gap-2 border-b border-panel-border px-4">
            <x-ui.icon name="search" class="size-5 text-panel-text-subtle" />
            <input type="text" data-cmdk-input class="h-14 min-w-0 flex-1 border-0 bg-transparent p-0 text-base text-panel-text placeholder:text-panel-text-subtle focus:outline-none focus:ring-0" placeholder="{{ $placeholder ?? __('admin.search') }}" autocomplete="off" role="combobox" aria-expanded="true" aria-controls="cmdk-results" aria-label="{{ $placeholder ?? __('admin.search') }}">
            <kbd class="hidden rounded-md border border-panel-border px-1.5 text-[10px] font-semibold text-panel-text-subtle md:inline" dir="ltr">Esc</kbd>
            <button type="button" data-cmdk-close class="ibtn ibtn-sm md:hidden" aria-label="{{ __('ui.close') }}"><x-ui.icon name="x" /></button>
        </div>
        <div id="cmdk-results" data-cmdk-results role="listbox" class="min-h-0 flex-1 overflow-y-auto p-2"></div>
        <div class="hidden items-center gap-4 border-t border-panel-border px-4 py-2 text-xs text-panel-text-subtle md:flex"><span>↑↓ {{ __('ui.cmdk.navigate') }}</span><span>↵ {{ __('ui.cmdk.open') }}</span><span>Esc {{ __('ui.close') }}</span></div>
    </div>
    <script type="application/json" data-cmdk-data>@json(['groups' => $groups, 'empty' => __('admin.cmdk_empty')])</script>
</div>
