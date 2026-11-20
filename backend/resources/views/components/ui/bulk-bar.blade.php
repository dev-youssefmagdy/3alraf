{{-- BulkBar (Figma BulkBar): appears when rows are selected; JS toggles `hidden` / fills [data-bulk-count]. Inverse surface so selection mode is unmistakable.
     Desktop: sticky bottom-centre. Mobile: full width above the bottom nav (icon buttons only). Slot = actions. --}}
@props(['count' => null])
<div {{ $attributes->merge(['class' => 'hidden fixed inset-x-3 bottom-[calc(var(--size-bottom-nav)+0.75rem)] z-30 items-center justify-between gap-3 rounded-2xl bg-panel-text px-4 py-2.5 text-sm font-bold text-panel-bg shadow-panel-lg md:inset-x-auto md:bottom-6 md:start-1/2 md:min-w-96 md:-translate-x-1/2 rtl:md:translate-x-1/2 [&:not(.hidden)]:flex']) }} data-bulk-bar>
    <span><span data-bulk-count>{{ $count }}</span> <span class="font-medium opacity-80">{{ __('ui.selected') }}</span></span>
    <div class="flex items-center gap-2 [&_.btn]:border-white/30 [&_.btn]:bg-transparent [&_.btn]:text-inherit">{{ $slot }}</div>
</div>
