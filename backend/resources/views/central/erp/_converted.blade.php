@if ($g['converted'])
    @php $c = $g['converted']; @endphp
    @if ($c['rates'])
    <div class="mt-3 rounded-lg border border-sky-200 bg-sky-50 p-3 text-xs text-sky-900" data-converted="{{ $g['currency'] }}">
        <strong>{{ __('erp_central.converted_label', ['from' => $g['currency'], 'to' => $c['to']]) }}</strong>:
        @foreach (['revenue', 'expenses', 'net'] as $k){{ __('erp_central.col.'.$k) }} {{ number_format((float) $c['totals'][$k], 2) }} {{ $c['to'] }}@if (! $loop->last) | @endif @endforeach
        <div class="mt-1">@foreach ($c['rates'] as $tid => $rt){{ $g['tenants']->firstWhere('id', $tid)['name'] }}: 1 {{ $g['currency'] }} = {{ rtrim(rtrim($rt['rate'], '0'), '.') }} {{ $c['to'] }} ({{ __('erp_central.rate_'.$rt['scope']) }}, {{ $rt['date'] }})@if (! $loop->last); @endif @endforeach</div>
    </div>
    @endif
    @if ($c['missing'])<div class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-xs font-semibold text-red-700" data-no-rate="{{ $g['currency'] }}">{{ __('erp_central.no_rate', ['tenants' => implode(', ', $c['missing']), 'from' => $g['currency'], 'to' => $c['to']]) }}</div>@endif
@endif
