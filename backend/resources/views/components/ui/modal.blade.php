{{-- Modal (Figma Modal): centred dialog on desktop, bottom sheet below 768 (same markup, CSS). Open with [data-modal-open="id"], close with [data-modal-close], Esc or the backdrop.
     size sm | md | lg. Slots: default = body, footer = actions. role=dialog aria-modal; focus is trapped and restored (ui.js). --}}
@props(['id', 'title' => null, 'size' => 'md'])
@php $w = ['sm' => 'md:max-w-sm', 'md' => 'md:max-w-lg', 'lg' => 'md:max-w-2xl'][$size] ?? 'md:max-w-lg'; @endphp
<div id="{{ $id }}" data-modal class="fixed inset-0 z-[60] hidden items-end justify-center md:items-center md:p-4" aria-hidden="true">
    <div data-modal-backdrop class="absolute inset-0"></div>
    <div role="dialog" aria-modal="true" @if ($title) aria-labelledby="{{ $id }}-t" @endif tabindex="-1" {{ $attributes->merge(['class' => "sheet relative flex max-h-[90dvh] flex-col $w"]) }}>
        <span class="mx-auto mt-2 h-1 w-10 rounded-full bg-panel-border-strong md:hidden" aria-hidden="true"></span>
        <div class="flex items-center justify-between gap-3 px-5 pb-2 pt-4 md:pt-5">
            <h3 id="{{ $id }}-t" class="text-panel-h3 font-bold text-panel-text">{{ $title }}</h3>
            <button type="button" data-modal-close class="ibtn ibtn-sm -me-2" aria-label="{{ __('ui.close') }}"><x-ui.icon name="x" /></button>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto px-5 pb-5 text-panel-body text-panel-text">{{ $slot }}</div>
        @if (isset($footer) && trim((string) $footer) !== '')<div class="flex flex-col-reverse gap-2 border-t border-panel-border px-5 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] sm:flex-row sm:justify-end">{{ $footer }}</div>@endif
    </div>
</div>
