<x-layouts.admin guard="central" :title="__('erp_central.cards.overview')[0]">
    @include('central.erp._back')
    @php $in = 'mt-1 block rounded border border-slate-300 px-2 py-1.5'; $n = fn ($v) => number_format((float) $v, 2);
        $sort = fn ($k) => route('central.erp.overview', array_merge($q, ['sort' => $k, 'dir' => (($q['sort'] ?? 'name') === $k && ($q['dir'] ?? 'asc') === 'asc') ? 'desc' : 'asc']));
        $cols = ['revenue', 'expenses', 'net', 'cash', 'ar', 'ap', 'inventory', 'equity']; @endphp
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <label>{{ __('erp_central.f.search') }}<input name="q" value="{{ $q['q'] ?? '' }}" class="{{ $in }}"></label>
            <label>{{ __('erp_central.f.currency') }}<select name="currency" class="{{ $in }}"><option value="">{{ __('erp_central.f.all') }}</option>@foreach ($currencies as $c)<option @selected(($q['currency'] ?? '') === $c)>{{ $c }}</option>@endforeach</select></label>
            <label>{{ __('erp_central.f.from') }}<input type="date" name="from" value="{{ $q['from'] ?? '' }}" class="{{ $in }}"></label>
            <label>{{ __('erp_central.f.to') }}<input type="date" name="to" value="{{ $q['to'] ?? '' }}" class="{{ $in }}"></label>
            <label>{{ __('erp_central.f.period') }}<select name="period" class="{{ $in }}"><option value="">{{ __('erp_central.f.all') }}</option>@foreach (['open', 'closed'] as $p)<option value="{{ $p }}" @selected(($q['period'] ?? '') === $p)>{{ __('erp_central.period_status.'.$p) }}</option>@endforeach</select></label>
            <label>{{ __('erp_central.f.anomalies') }}<select name="anomalies" class="{{ $in }}"><option value="">{{ __('erp_central.f.all') }}</option><option value="yes" @selected(($q['anomalies'] ?? '') === 'yes')>{{ __('erp_central.f.with_anomalies') }}</option></select></label>
            <x-button type="submit">{{ __('erp_central.f.filter') }}</x-button>
            @if (auth('central')->user()?->can('erp_central.reports'))<a class="rounded border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50" href="{{ route('central.erp.overview.export', $q) }}">{{ __('erp_central.export_csv') }}</a>@endif
        </form>
    </x-card>
    <p class="mb-3 text-xs text-amber-700">{{ __('erp_central.no_mixing') }}</p>
    <x-card>
        <div class="overflow-x-auto"><table class="w-full text-sm" data-overview>
            <thead><tr class="border-b text-slate-500">
                <th class="py-2 text-start"><a href="{{ $sort('name') }}">{{ __('erp_central.col.tenant') }}</a></th><th class="text-start">{{ __('erp_central.col.currency') }}</th>
                @foreach ($cols as $c)<th class="text-end"><a href="{{ $sort($c) }}">{{ __('erp_central.col.'.$c) }}</a></th>@endforeach
                <th class="text-start ps-3"><a href="{{ $sort('last_posting') }}">{{ __('erp_central.col.last_posting') }}</a></th><th class="text-start">{{ __('erp_central.col.periods') }}</th>
                <th class="text-end"><a href="{{ $sort('anomalies') }}">{{ __('erp_central.col.anomalies') }}</a></th>
            </tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr class="border-b border-slate-100" data-tenant="{{ $r['id'] }}">
                    <td class="py-1.5"><a class="font-medium text-brand-600" href="{{ route('central.erp.tenant', $r['id']) }}">{{ $r['name'] }}</a></td><td>{{ $r['currency'] }}</td>
                    @foreach ($cols as $c)<td class="text-end tabular-nums {{ $c === 'net' && (float) $r[$c] < 0 ? 'text-red-600' : '' }}" data-{{ $c }}>{{ $n($r[$c]) }}</td>@endforeach
                    <td class="ps-3">{{ $r['last_posting'] ?? '-' }}</td>
                    <td>@if (! $r['has_erp'])-@else<span class="rounded-full px-2 py-0.5 text-[11px] {{ $r['periods_open'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">{{ $r['periods_open'] ? __('erp_central.period_status.open') : __('erp_central.period_status.closed') }}</span> <span class="text-xs text-slate-500">{{ $r['periods_open'] }}/{{ $r['periods_closed'] }}</span>@endif</td>
                    <td class="text-end">@if ($r['anomalies'])<a class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700" href="{{ route('central.erp.anomalies', ['tenant_id' => $r['id']]) }}">{{ $r['anomalies'] }}</a>@else 0 @endif</td>
                </tr>
            @empty<tr><td colspan="14" class="py-4 text-slate-500">{{ __('erp_central.empty') }}</td></tr>@endforelse
            </tbody>
        </table></div>
        <p class="mt-3 text-xs text-slate-500">{{ __('erp_central.anomaly_fast_note') }}</p>
    </x-card>
</x-layouts.admin>
