{{-- $chart {type,labels,datasets}; rendered by resources/js/lib/reports.js (Chart.js) --}}
@if (! empty($chart) && ! empty($chart['labels']))
    <div class="relative h-72"><canvas data-chart='@json($chart)'></canvas></div>
@else
    <p class="py-10 text-center text-sm text-slate-400">{{ __('admin.no_data') }}</p>
@endif
