{{-- Accordion (Figma Accordion): hides advanced settings (SEO). Native <details>, so it is keyboard and screen-reader ready. --}}
@props(['title', 'open' => false])
<details {{ $attributes->merge(['class' => 'group rounded-2xl border border-panel-border bg-panel-surface']) }} @if ($open) open @endif>
    <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-5 py-3 text-sm font-semibold text-panel-text [&::-webkit-details-marker]:hidden">{{ $title }}<x-ui.icon name="chevron-down" class="size-4 text-panel-text-subtle transition group-open:rotate-180" /></summary>
    <div class="border-t border-panel-border px-5 py-4">{{ $slot }}</div>
</details>
