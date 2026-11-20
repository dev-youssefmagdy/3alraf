{{-- $chart {type,labels,datasets}; rendered by resources/js/lib/reports.js (Chart.js, coloured from the panel tokens). Donut charts become a ranked list below 768 (spec 4.6). --}}
@if (! empty($chart) && ! empty($chart['labels']))
    @php($pie = ($chart['type'] ?? '') === 'doughnut')
    <div class="relative h-60 min-w-[280px] md:h-72 {{ $pie ? 'max-md:hidden' : '' }}"><canvas data-chart='@json($chart)' role="img" aria-label="{{ implode(', ', array_slice($chart['labels'], 0, 6)) }}"></canvas></div>
    @if ($pie)
        @php($vals = array_map('floatval', $chart['datasets'][0]['data'] ?? []))
        @php($sum = array_sum($vals) ?: 1)
        <ol class="space-y-3 md:hidden">
            @foreach ($chart['labels'] as $i => $l)
                @php($share = round(($vals[$i] ?? 0) / $sum * 100))
                <li><div class="flex items-baseline justify-between gap-2 text-sm"><span class="font-medium text-panel-text">{{ $l }}</span><span class="tabular-nums text-panel-text-muted" dir="ltr">{{ number_format($vals[$i] ?? 0) }} · {{ $share }}%</span></div>
                    <x-ui.progress :value="$share" class="mt-1" /></li>
            @endforeach
        </ol>
    @endif
@else
    <x-ui.empty-state kind="nodata" :title="__('admin.no_data')" :message="''" class="!py-8" />
@endif
