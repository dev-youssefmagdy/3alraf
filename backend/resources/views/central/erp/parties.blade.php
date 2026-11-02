<x-layouts.admin guard="central" :title="__('erp_central.cards.parties')[0]">
    @include('central.erp._back')
    @php $in = 'mt-1 block rounded border border-slate-300 px-2 py-1.5'; $n = fn ($v) => number_format((float) $v, 2); @endphp
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <label>{{ __('erp_central.col.tenant') }}<select name="tenant_id" class="{{ $in }}"><option value="">{{ __('erp_central.f.all') }}</option>@foreach ($tenants as $t)<option value="{{ $t->id }}" @selected(($q['tenant_id'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach</select></label>
            <label><input type="checkbox" name="ageing" value="1" @checked(! empty($q['ageing']))> {{ __('erp_central.f.ageing') }}</label>
            <x-button type="submit">{{ __('erp_central.f.filter') }}</x-button>
        </form>
    </x-card>
    <p class="mb-3 text-xs text-amber-700">{{ __('erp_central.no_mixing') }}</p>
    @foreach ($parties as $tid => $p)
        <x-card :title="$p['tenant']->name.' ('.$p['currency'].')'" class="mb-5">
            <div class="grid gap-5 lg:grid-cols-2" data-tenant="{{ $tid }}">
                @foreach (['ar' => 'customers', 'ap' => 'suppliers'] as $k => $lbl)
                    <div>
                        <div class="mb-1 flex items-baseline justify-between"><h3 class="font-semibold">{{ __('erp_central.'.($k === 'ar' ? 'receivables' : 'payables')) }}</h3><span class="text-lg font-bold tabular-nums" data-total="{{ $k }}">{{ $n($p[$k]['total']) }} {{ $p['currency'] }}</span></div>
                        <p class="mb-2 text-xs text-slate-500">{{ $p[$k]['parties'] }} {{ __('erp_central.open_parties') }} @if ((float) $p[$k]['credit'] != 0) | {{ __('erp_central.credit_balances') }}: {{ $n($p[$k]['credit']) }} @endif</p>
                        <table class="w-full text-sm"><tbody>@forelse ($p[$k]['top'] as $x)<tr class="border-b border-slate-100"><td class="py-1">{{ $x['name'] }}</td><td class="text-end tabular-nums">{{ $n($x['balance']) }}</td></tr>@empty<tr><td class="py-2 text-slate-500">{{ __('erp_central.empty') }}</td></tr>@endforelse</tbody></table>
                        @if ($ageing && isset($ageing[$tid]))
                            @php $a = $ageing[$tid][$k]; @endphp
                            <div class="mt-2 grid grid-cols-5 gap-1 text-center text-xs" data-ageing="{{ $k }}">@foreach (['b0' => '0-30', 'b31' => '31-60', 'b61' => '61-90', 'b90' => '90+', 'total' => 'Total'] as $bk => $bl)<div class="rounded bg-slate-50 p-1"><div class="text-slate-500">{{ $bl }}</div><div class="font-semibold tabular-nums">{{ $n($a[$bk]) }}</div></div>@endforeach</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-card>
    @endforeach
</x-layouts.admin>
