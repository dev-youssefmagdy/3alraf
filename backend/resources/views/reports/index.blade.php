<x-layouts.admin :title="__('reports.reports')">
    <x-slot:header><x-ui.page-header :title="__('reports.reports')" /></x-slot:header>
    @include('reports._filter', ['f' => $f, 'branches' => []])
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($keys as $k)
            <a href="{{ route('admin.reports.show', ['report' => $k] + $f->query()) }}" class="rounded-xl border border-panel-border bg-panel-surface p-4 shadow-sm transition hover:border-panel-accent">
                <p class="font-semibold text-panel-text">{{ __("reports.titles.$k") }}</p>
                <p class="mt-1 text-xs text-panel-text-subtle">{{ __("reports.desc.$k") }}</p>
            </a>
        @endforeach
    </div>
</x-layouts.admin>
