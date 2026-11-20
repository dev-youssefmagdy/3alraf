{{-- Toast region (Figma Toast): live region + one <template> per tone for the JS toast() helper (ui.js). Top-end on desktop, bottom-centre above the bottom nav on mobile. 4s; errors stay until dismissed. --}}
<div data-toast-region aria-live="polite" class="pointer-events-none fixed inset-x-3 bottom-[calc(var(--size-bottom-nav)+1rem)] z-[80] flex flex-col items-center gap-2 md:inset-x-auto md:bottom-auto md:end-6 md:top-20 md:items-end"></div>
@foreach (['success', 'danger', 'info', 'warning'] as $tone)
    <template data-toast-template="{{ $tone }}"><x-ui.toast :tone="$tone" message="" :action="__('ui.undo')" /></template>
@endforeach
