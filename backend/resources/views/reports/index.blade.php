<x-layouts.admin :guard="$guard" :title="__('reports.reports')">
    @include('reports._filter', ['f' => $f, 'branches' => []])
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($keys as $k)
            <a href="{{ route($guard.'.reports.show', ['report' => $k] + $f->query()) }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-brand-500">
                <p class="font-semibold text-slate-800">{{ __("reports.titles.$k") }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ __("reports.desc.$k") }}</p>
            </a>
        @endforeach
    </div>
</x-layouts.admin>
