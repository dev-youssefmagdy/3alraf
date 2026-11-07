<x-layouts.admin guard="central" :title="__('erp_central.cards.anomalies')[0]">
    @include('central.erp._back')
    @php $in = 'mt-1 block rounded border border-slate-300 px-2 py-1.5'; $sev = ['high' => 'bg-red-100 text-red-700', 'medium' => 'bg-amber-100 text-amber-800', 'low' => 'bg-slate-200 text-slate-600']; @endphp
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <label>{{ __('erp_central.col.tenant') }}<select name="tenant_id" class="{{ $in }}"><option value="">{{ __('erp_central.f.all') }}</option>@foreach ($allTenants as $t)<option value="{{ $t->id }}" @selected(($q['tenant_id'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach</select></label>
            <label>{{ __('erp_central.col.severity') }}<select name="severity" class="{{ $in }}"><option value="">{{ __('erp_central.f.all') }}</option>@foreach (['high', 'medium', 'low'] as $s)<option value="{{ $s }}" @selected(($q['severity'] ?? '') === $s)>{{ __('erp_central.severity.'.$s) }}</option>@endforeach</select></label>
            <label>{{ __('erp_central.col.check') }}<select name="code" class="{{ $in }}"><option value="">{{ __('erp_central.f.all') }}</option>@foreach (\App\Services\Erp\Central\AnomalyService::CODES as $c)<option value="{{ $c }}" @selected(($q['code'] ?? '') === $c)>{{ __('erp_central.an.'.$c)[0] }}</option>@endforeach</select></label>
            <label><input type="checkbox" name="deep" value="1" @checked(! empty($q['deep']))> {{ __('erp_central.f.deep') }}</label>
            <x-button type="submit">{{ __('erp_central.f.filter') }}</x-button>
            <a class="rounded border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50" href="{{ route('central.erp.anomalies.export', $q) }}">{{ __('erp_central.export_csv') }}</a>
        </form>
    </x-card>
    <x-card>
        <div class="overflow-x-auto"><table class="w-full text-sm" data-anomalies>
            <thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp_central.col.severity') }}</th><th class="text-start">{{ __('erp_central.col.tenant') }}</th><th class="text-start">{{ __('erp_central.col.check') }}</th><th class="text-end">{{ __('erp_central.col.count') }}</th><th class="text-start ps-3">{{ __('erp_central.col.detail') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($rows as $a)
                @php $t = $tenants[$a['tenant_id']] ?? null; @endphp
                <tr class="border-b border-slate-100" data-code="{{ $a['code'] }}" data-tenant="{{ $a['tenant_id'] }}">
                    <td class="py-1.5"><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $sev[$a['severity']] }}">{{ __('erp_central.severity.'.$a['severity']) }}</span></td>
                    <td>{{ $t?->name ?? '#'.$a['tenant_id'] }} <span class="text-xs text-slate-500">{{ $t?->currency_code }}</span></td>
                    <td><div class="font-medium">{{ __('erp_central.an.'.$a['code'])[0] }}</div><div class="text-xs text-slate-500">{{ __('erp_central.an.'.$a['code'])[1] }}</div></td>
                    <td class="text-end tabular-nums">{{ $a['count'] }}</td><td class="ps-3 text-xs">{{ $a['detail'] }}</td>
                    <td class="text-end whitespace-nowrap">@if ($a['entry_id'])<a class="text-brand-600" href="{{ route('central.erp.transactions.show', $a['entry_id']) }}">{{ __('erp_central.entry') }}</a> @endif<a class="text-brand-600" href="{{ route('central.erp.tenant', $a['tenant_id']) }}">{{ __('erp_central.drill') }}</a></td>
                </tr>
            @empty<tr><td colspan="6" class="py-4 text-emerald-700">{{ __('erp_central.no_anomalies') }}</td></tr>@endforelse
            </tbody>
        </table></div>
    </x-card>
</x-layouts.admin>
