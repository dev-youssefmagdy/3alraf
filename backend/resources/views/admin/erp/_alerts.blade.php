{{-- rows from AnomalyService. usage: @include('admin.erp._alerts', ['alerts' => $rows]) --}}
@php $sev = ['high' => 'bg-panel-danger-bg text-panel-danger-fg', 'medium' => 'bg-panel-warning-bg text-panel-warning-fg', 'low' => 'bg-panel-border text-panel-text-muted']; @endphp
@foreach ($alerts as $a)
    <div class="flex items-start gap-3 border-b border-panel-border py-2 text-sm" data-alert="{{ $a['code'] }}">
        <span class="mt-0.5 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $sev[$a['severity']] }}">{{ __('erp_audit.severity.'.$a['severity']) }}</span>
        <div><div class="font-medium text-panel-text">{{ __('erp_audit.an.'.$a['code'])[0] }} <span class="text-panel-text-subtle">({{ $a['count'] }})</span></div><div class="text-xs text-panel-text-subtle">{{ __('erp_audit.an.'.$a['code'])[1] }}@if ($a['detail']) - {{ $a['detail'] }}@endif</div></div>
    </div>
@endforeach
@can('erp.audit.view')<a href="{{ route('admin.erp.anomalies') }}" class="mt-3 inline-block text-sm text-panel-accent-text">{{ __('erp.cards.anomalies')[0] }} &rarr;</a>@endcan
