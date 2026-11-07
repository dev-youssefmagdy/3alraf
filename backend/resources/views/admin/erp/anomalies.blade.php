<x-layouts.admin :title="__('erp.cards.anomalies')[0]">
    @include('admin.erp._back')
    @php $in = 'mt-1 block rounded border border-slate-300 px-2 py-1.5'; $sev = ['high' => 'bg-red-100 text-red-700', 'medium' => 'bg-amber-100 text-amber-800', 'low' => 'bg-slate-200 text-slate-600']; @endphp
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <label>{{ __('erp_audit.col.severity') }}<select name="severity" class="{{ $in }}"><option value="">{{ __('erp_audit.f.all') }}</option>@foreach (['high', 'medium', 'low'] as $s)<option value="{{ $s }}" @selected(($q['severity'] ?? '') === $s)>{{ __('erp_audit.severity.'.$s) }}</option>@endforeach</select></label>
            <label>{{ __('erp_audit.col.check') }}<select name="code" class="{{ $in }}"><option value="">{{ __('erp_audit.f.all') }}</option>@foreach (\App\Services\Erp\Audit\AnomalyService::CODES as $c)<option value="{{ $c }}" @selected(($q['code'] ?? '') === $c)>{{ __('erp_audit.an.'.$c)[0] }}</option>@endforeach</select></label>
            <label><input type="checkbox" name="deep" value="1" @checked(! empty($q['deep']))> {{ __('erp_audit.f.deep') }}</label>
            <x-button type="submit">{{ __('erp_audit.f.filter') }}</x-button>
            <a class="rounded border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50" href="{{ route('admin.erp.anomalies.export', $q) }}">{{ __('erp_audit.export_csv') }}</a>
        </form>
    </x-card>
    <x-card>
        <div class="overflow-x-auto"><table class="w-full text-sm" data-anomalies>
            <thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp_audit.col.severity') }}</th><th class="text-start">{{ __('erp_audit.col.check') }}</th><th class="text-end">{{ __('erp_audit.col.count') }}</th><th class="text-start ps-3">{{ __('erp_audit.col.detail') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($rows as $a)
                <tr class="border-b border-slate-100" data-code="{{ $a['code'] }}">
                    <td class="py-1.5"><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $sev[$a['severity']] }}">{{ __('erp_audit.severity.'.$a['severity']) }}</span></td>
                    <td><div class="font-medium">{{ __('erp_audit.an.'.$a['code'])[0] }}</div><div class="text-xs text-slate-500">{{ __('erp_audit.an.'.$a['code'])[1] }}</div></td>
                    <td class="text-end tabular-nums">{{ $a['count'] }}</td><td class="ps-3 text-xs">{{ $a['detail'] }}</td>
                    <td class="text-end whitespace-nowrap">@if ($a['entry_id'])@can('erp.journals.view')<a class="text-brand-600" href="{{ route('admin.erp.journals.show', $a['entry_id']) }}">{{ __('erp_audit.entry') }}</a>@endcan @endif</td>
                </tr>
            @empty<tr><td colspan="5" class="py-4 text-emerald-700">{{ __('erp_audit.no_anomalies') }}</td></tr>@endforelse
            </tbody>
        </table></div>
    </x-card>
</x-layouts.admin>
