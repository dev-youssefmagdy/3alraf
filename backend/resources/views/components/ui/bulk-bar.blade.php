{{-- Bulk actions bar (Figma Panel/Bulk actions bar): shown when rows are selected; JS toggles `hidden` and fills [data-bulk-count]. --}}
<div {{ $attributes->merge(['class' => 'hidden flex-wrap items-center justify-between gap-3 rounded-2xl bg-panel-soft px-4 py-2.5 text-sm font-bold text-panel-text']) }} data-bulk-bar>
    <span data-bulk-count></span>
    <div class="flex flex-wrap items-center gap-3">{{ $slot }}</div>
</div>
