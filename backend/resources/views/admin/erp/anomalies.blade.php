<x-layouts.admin :title="__('erp.cards.anomalies')[0]">
    <x-slot:header><x-ui.page-header :title="__('erp.cards.anomalies')[0]" /></x-slot:header>
    @include('admin.erp._back')
    @php $in = 'form-control mt-1 !w-auto'; $sev = ['high' => 'bg-panel-danger-bg text-panel-danger-fg', 'medium' => 'bg-panel-warning-bg text-panel-warning-fg', 'low' => 'bg-panel-border text-panel-text-muted']; @endphp
    <x-ui.card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <label>{{ __('erp_audit.col.severity') }}<select name="severity" class="{{ $in }}"><option value="">{{ __('erp_audit.f.all') }}</option>@foreach (['high', 'medium', 'low'] as $s)<option value="{{ $s }}" @selected(($q['severity'] ?? '') === $s)>{{ __('erp_audit.severity.'.$s) }}</option>@endforeach</select></label>
            <label>{{ __('erp_audit.col.check') }}<select name="code" class="{{ $in }}"><option value="">{{ __('erp_audit.f.all') }}</option>@foreach (\App\Services\Erp\Audit\AnomalyService::CODES as $c)<option value="{{ $c }}" @selected(($q['code'] ?? '') === $c)>{{ __('erp_audit.an.'.$c)[0] }}</option>@endforeach</select></label>
            <label><input type="checkbox" name="deep" value="1" @checked(! empty($q['deep']))> {{ __('erp_audit.f.deep') }}</label>
            <x-ui.button type="submit">{{ __('erp_audit.f.filter') }}</x-ui.button>
            <a class="rounded border border-panel-border-strong px-3 py-1.5 text-panel-text hover:bg-panel-surface-sunken" href="{{ route('admin.erp.anomalies.export', $q) }}">{{ __('erp_audit.export_csv') }}</a>
        </form>
    </x-ui.card>
    <x-ui.card>
        <x-ui.table data-anomalies>
            <thead><tr><th>{{ __('erp_audit.col.severity') }}</th><th>{{ __('erp_audit.col.check') }}</th><th class="text-end">{{ __('erp_audit.col.count') }}</th><th class="ps-3">{{ __('erp_audit.col.detail') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($rows as $a)
                <tr data-code="{{ $a['code'] }}">
                    <td data-label=""><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $sev[$a['severity']] }}">{{ __('erp_audit.severity.'.$a['severity']) }}</span></td>
                    <td data-label="{{ __('erp_audit.col.check') }}"><div class="font-medium">{{ __('erp_audit.an.'.$a['code'])[0] }}</div><div class="text-xs text-panel-text-subtle">{{ __('erp_audit.an.'.$a['code'])[1] }}</div></td>
                    <td data-label="{{ __('erp_audit.col.count') }}" class="text-end tabular-nums">{{ $a['count'] }}</td><td data-label="{{ __('erp_audit.col.detail') }}" class="ps-3">{{ $a['detail'] }}</td>
                    <td class="ui-table-actions text-end whitespace-nowrap">@if ($a['entry_id'])@can('erp.journals.view')<a class="text-panel-accent-text" href="{{ route('admin.erp.journals.show', $a['entry_id']) }}">{{ __('erp_audit.entry') }}</a>@endcan @endif</td>
                </tr>
            @empty<tr><td colspan="5" class="text-panel-success-fg">{{ __('erp_audit.no_anomalies') }}</td></tr>@endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>
</x-layouts.admin>
