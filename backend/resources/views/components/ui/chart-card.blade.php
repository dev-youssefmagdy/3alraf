{{-- ChartCard (Figma ChartCard): title, subtitle (range) and optional range pills in the header; the chart (Chart.js canvas, coloured from the CSS tokens) in the slot.
     ranges = [label => url] of segmented links; active = the current label. Charts need at least 280px: below that show numbers only. --}}
@props(['title', 'subtitle' => null, 'ranges' => [], 'active' => null])
<x-ui.card :title="$title" :subtitle="$subtitle" {{ $attributes }}>
    @if ($ranges)
        <x-slot:actions>
            <div class="inline-flex rounded-full bg-panel-surface-sunken p-0.5" role="group">@foreach ($ranges as $l => $u)<a href="{{ $u }}" class="min-h-8 rounded-full px-3 text-xs font-bold leading-8 {{ (string) $active === (string) $l ? 'bg-panel-surface text-panel-text shadow-panel-xs' : 'text-panel-text-muted' }}" @if ((string) $active === (string) $l) aria-current="true" @endif>{{ $l }}</a>@endforeach</div>
        </x-slot:actions>
    @endif
    <div class="min-w-0 overflow-x-auto">{{ $slot }}</div>
</x-ui.card>
