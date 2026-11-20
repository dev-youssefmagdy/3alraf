{{-- FilterToolbar (Figma FilterToolbar): search + filter chips/fields + actions. Desktop: inline row. Mobile: the filters collapse behind a "Filters" button into a bottom sheet
     (same element, CSS + [data-sheet-toggle] in ui.js). Slots: default = filter fields (kept inside the toolbar), actions = primary action at the end side. --}}
@props(['id' => 'filters'])
<div {{ $attributes->merge(['class' => 'mb-4 flex flex-wrap items-center gap-3']) }} data-toolbar>
    @isset($search)<div class="min-w-0 flex-1 md:max-w-sm">{{ $search }}</div>@endisset
    @if (trim((string) $slot) !== '')
        <button type="button" data-sheet-toggle="{{ $id }}" class="btn btn-secondary btn-sm md:hidden"><x-ui.icon name="filter" class="size-4" />{{ __('ui.filters') }}</button>
        <div data-sheet-backdrop="{{ $id }}" class="fixed inset-0 z-40 hidden bg-panel-overlay/50 md:hidden"></div>
        <div id="{{ $id }}" data-sheet class="w-full max-md:fixed max-md:inset-x-0 max-md:bottom-0 max-md:z-50 max-md:hidden max-md:max-h-[85dvh] max-md:overflow-y-auto max-md:rounded-t-3xl max-md:bg-panel-surface max-md:p-5 max-md:shadow-panel-overlay max-md:data-[open]:block md:contents">
            <div class="mb-4 flex items-center justify-between md:hidden"><h3 class="text-panel-h3 font-bold">{{ __('ui.filters') }}</h3><button type="button" data-sheet-toggle="{{ $id }}" class="ibtn ibtn-sm" aria-label="{{ __('ui.close') }}"><x-ui.icon name="x" /></button></div>
            {{ $slot }}
        </div>
    @endif
    @isset($actions)<div class="ms-auto flex items-center gap-2 max-md:w-full">{{ $actions }}</div>@endisset
</div>
