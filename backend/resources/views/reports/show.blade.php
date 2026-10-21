<x-layouts.admin :guard="$guard" :title="$data['title']">
    <div class="mb-3 flex flex-wrap items-center gap-3 text-sm text-slate-500">
        <a href="{{ route($guard.'.reports.index', $f->query()) }}" class="text-brand-600 hover:underline">&larr; {{ __('reports.reports') }}</a>
        <span>{{ __('reports.amounts_in', ['c' => $data['currency']]) }}@if ($guard === 'central') &middot; {{ $tenant ? $tenant->name : __('admin.all_tenants') }}@endif</span>
    </div>
    @include('reports._filter', ['f' => $f, 'branches' => $branches, 'snapshot' => $data['snapshot']])
    @include('reports._cards', ['cards' => $data['cards']])
    <div class="grid gap-4 xl:grid-cols-3">
        <x-card :title="__('reports.chart')" class="xl:col-span-1">@include('reports._chart', ['chart' => $data['chart']])</x-card>
        <x-card :title="$data['title']" class="xl:col-span-2">
            <x-slot:actions>
                <a href="{{ route($guard.'.reports.export', ['report' => $key] + $f->query()) }}" class="btn-sm btn-secondary">{{ __('reports.export_csv') }}</a>
            </x-slot:actions>
            @include('reports._table', ['columns' => $data['columns'], 'rows' => $data['rows']])
        </x-card>
    </div>
</x-layouts.admin>
