{{-- Drawer (Figma Drawer): side panel from the end edge (filters, quick edit) that becomes a bottom sheet below 768. Open with [data-modal-open="id"] like <x-ui.modal>. --}}
@props(['id', 'title' => null])
<div id="{{ $id }}" data-modal class="fixed inset-0 z-[60] hidden justify-end max-md:items-end" aria-hidden="true">
    <div data-modal-backdrop class="absolute inset-0"></div>
    <aside role="dialog" aria-modal="true" @if ($title) aria-labelledby="{{ $id }}-t" @endif tabindex="-1" {{ $attributes->merge(['class' => 'relative flex max-h-[90dvh] w-full flex-col bg-panel-surface shadow-panel-overlay max-md:rounded-t-3xl max-md:animate-[sheet-in_var(--duration-slow)_var(--ease-panel)] md:h-full md:max-h-none md:max-w-md md:animate-[drawer-in-end_var(--duration-slow)_var(--ease-panel)] rtl:md:[animation-direction:normal]']) }}>
        <div class="flex items-center justify-between gap-3 border-b border-panel-border px-5 py-4">
            <h3 id="{{ $id }}-t" class="text-panel-h3 font-bold text-panel-text">{{ $title }}</h3>
            <button type="button" data-modal-close class="ibtn ibtn-sm -me-2" aria-label="{{ __('ui.close') }}"><x-ui.icon name="x" /></button>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto p-5">{{ $slot }}</div>
        @isset($footer)<div class="flex gap-2 border-t border-panel-border px-5 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">{{ $footer }}</div>@endisset
    </aside>
</div>
