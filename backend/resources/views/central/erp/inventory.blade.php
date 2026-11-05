<x-layouts.admin guard="central" :title="__('erp_central.cards.inventory')[0]">
    @include('central.erp._back')
    @php $n = fn ($v) => number_format((float) $v, 2); @endphp
    <p class="mb-3 text-xs text-amber-700">{{ __('erp_central.no_mixing') }}</p>
    <x-card>
        <div class="overflow-x-auto"><table class="w-full text-sm" data-inventory>
            <thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp_central.col.tenant') }}</th><th class="text-start">{{ __('erp_central.col.currency') }}</th><th class="text-end">{{ __('erp_central.col.ledger') }}</th><th class="text-end">{{ __('erp_central.col.transit') }}</th><th class="text-end">{{ __('erp_central.col.stock') }}</th><th class="text-end">{{ __('erp_central.col.units') }}</th><th class="text-end">{{ __('erp_central.col.difference') }}</th><th class="text-start ps-3">{{ __('erp_central.col.status') }}</th></tr></thead>
            <tbody>@foreach ($rows as $r)<tr class="border-b border-slate-100" data-tenant="{{ $r['id'] }}"><td class="py-1.5"><a class="text-brand-600" href="{{ route('central.erp.tenant', $r['id']) }}">{{ $r['name'] }}</a></td><td>{{ $r['currency'] }}</td><td class="text-end tabular-nums">{{ $n($r['ledger']) }}</td><td class="text-end tabular-nums">{{ $n($r['transit']) }}</td><td class="text-end tabular-nums">{{ $n($r['stock']) }}</td><td class="text-end">{{ number_format($r['units']) }}</td><td class="text-end tabular-nums {{ $r['ok'] ? '' : 'text-red-600' }}">{{ $n($r['difference']) }}</td><td class="ps-3">{{ $r['ok'] ? __('erp_central.ok') : __('erp_central.mismatch') }}</td></tr>@endforeach</tbody>
        </table></div>
    </x-card>
</x-layouts.admin>
