{{-- rows from AnomalyService (tenant side: one tenant). usage: @include('central.erp._alerts', ['alerts' => $rows]) --}}
@php $sev = ['high' => 'bg-red-100 text-red-700', 'medium' => 'bg-amber-100 text-amber-800', 'low' => 'bg-slate-200 text-slate-600']; @endphp
@foreach ($alerts as $a)
    <div class="flex items-start gap-3 border-b border-slate-100 py-2 text-sm" data-alert="{{ $a['code'] }}">
        <span class="mt-0.5 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $sev[$a['severity']] }}">{{ __('erp_central.severity.'.$a['severity']) }}</span>
        <div><div class="font-medium text-slate-800">{{ __('erp_central.an.'.$a['code'])[0] }} <span class="text-slate-500">({{ $a['count'] }})</span></div><div class="text-xs text-slate-500">{{ __('erp_central.an.'.$a['code'])[1] }}@if ($a['detail']) - {{ $a['detail'] }}@endif</div></div>
    </div>
@endforeach
