<x-layouts.admin guard="central" :title="__('erp_central.cards.consolidated')[0]">
    @include('central.erp._back')
    @include('central.erp._group_form')
    @php $n = fn ($v) => number_format((float) $v, 2); $cols = ['revenue', 'expenses', 'net', 'cash', 'ar', 'ap', 'inventory', 'equity']; @endphp
    @forelse ($groups as $cur => $g)
        <x-card :title="__('erp_central.consolidated_view', ['c' => $cur])" class="mb-5">
            <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-3" data-consolidated="{{ $cur }}">
                <div class="mb-2 text-xs font-semibold uppercase text-slate-500">{{ __('erp_central.consolidated_label') }} - {{ $cur }} ({{ $g['tenants']->count() }} {{ __('erp_central.tenants_n') }})</div>
                <div class="grid gap-3 sm:grid-cols-4">@foreach ($cols as $k)<div><div class="text-xs text-slate-500">{{ __('erp_central.col.'.$k) }}</div><div class="text-lg font-bold tabular-nums {{ $k === 'net' && (float) $g['totals'][$k] < 0 ? 'text-red-600' : '' }}">{{ $n($g['totals'][$k]) }}</div></div>@endforeach</div>
            </div>
            <div class="mb-1 text-xs font-semibold uppercase text-slate-500">{{ __('erp_central.breakdown') }}</div>
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp_central.col.tenant') }}</th>@foreach ($cols as $k)<th class="text-end">{{ __('erp_central.col.'.$k) }}</th>@endforeach</tr></thead>
                <tbody>@foreach ($g['tenants'] as $r)<tr class="border-b border-slate-100"><td class="py-1.5"><a class="text-brand-600" href="{{ route('central.erp.tenant', $r['id']) }}">{{ $r['name'] }}</a></td>@foreach ($cols as $k)<td class="text-end tabular-nums">{{ $n($r[$k]) }}</td>@endforeach</tr>@endforeach</tbody>
            </table></div>
            @include('central.erp._converted')
        </x-card>
    @empty<x-card>{{ __('erp_central.empty') }}</x-card>@endforelse
    <div class="mt-3 flex gap-2 text-sm">
        <a class="rounded border border-slate-300 px-3 py-1.5 hover:bg-slate-50" href="{{ route('central.erp.overview.export') }}">{{ __('erp_central.export_overview') }}</a>
        @if (auth('central')->user()?->can('erp_central.audit'))<a class="rounded border border-slate-300 px-3 py-1.5 hover:bg-slate-50" href="{{ route('central.erp.anomalies.export') }}">{{ __('erp_central.export_anomalies') }}</a>@endif
    </div>
</x-layouts.admin>
