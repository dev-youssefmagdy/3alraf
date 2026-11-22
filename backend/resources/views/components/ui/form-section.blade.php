{{-- FormSection (Figma FormSection): card with a description column (title + text, optional `actions` slot) and the fields (slot) in a 2-column grid on desktop; stacked below 1024. --}}
@props(['title', 'description' => null, 'cols' => 2, 'stacked' => false])
<section {{ $attributes->merge(['class' => 'grid gap-5 rounded-2xl border border-panel-border bg-panel-surface p-5 shadow-panel-xs '.($stacked ? '' : 'lg:grid-cols-[minmax(0,260px)_minmax(0,1fr)] lg:gap-8 ').'lg:p-6']) }}>
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 class="text-panel-h4 font-semibold text-panel-text">{{ $title }}</h2>
            @if ($description)<p class="mt-1 text-panel-small text-panel-text-muted">{{ $description }}</p>@endif
        </div>
        @isset($actions)<div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
    </div>
    <div class="grid min-w-0 grid-cols-1 content-start gap-4 {{ $cols === 2 ? 'md:grid-cols-2' : '' }} [&>[data-span-all]]:col-span-full">{{ $slot }}</div>
</section>
