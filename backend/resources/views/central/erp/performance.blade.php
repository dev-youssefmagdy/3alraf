<x-layouts.admin guard="central" :title="__('erp_central.cards.performance')[0]">
    @include('central.erp._back')
    @include('central.erp._group_form')
    @php $n = fn ($v) => number_format((float) $v, 2); @endphp
    @forelse ($groups as $cur => $g)
        <x-card :title="__('erp_central.currency_group', ['c' => $cur])" class="mb-5">
            <div class="overflow-x-auto"><table class="w-full text-sm" data-group="{{ $cur }}">
                <thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp_central.col.tenant') }}</th>@foreach (['revenue', 'expenses', 'net'] as $k)<th class="text-end">{{ __('erp_central.col.'.$k) }}</th>@endforeach<th class="text-end">{{ __('erp_central.col.margin') }}</th><th class="text-end">{{ __('erp_central.col.share') }}</th><th class="text-end">{{ __('erp_central.col.rank') }}</th></tr></thead>
                <tbody>
                @php $ranked = $g['tenants']->sortByDesc(fn ($r) => (float) $r['net'])->values(); @endphp
                @foreach ($g['tenants'] as $r)
                    @php $share = (float) $g['totals']['revenue'] > 0 ? (float) $r['revenue'] / (float) $g['totals']['revenue'] * 100 : 0; $margin = (float) $r['revenue'] > 0 ? (float) $r['net'] / (float) $r['revenue'] * 100 : 0; @endphp
                    <tr class="border-b border-slate-100"><td class="py-1.5"><a class="text-brand-600" href="{{ route('central.erp.tenant', $r['id']) }}">{{ $r['name'] }}</a></td>
                        <td class="text-end tabular-nums">{{ $n($r['revenue']) }}</td><td class="text-end tabular-nums">{{ $n($r['expenses']) }}</td><td class="text-end tabular-nums {{ (float) $r['net'] < 0 ? 'text-red-600' : '' }}">{{ $n($r['net']) }}</td>
                        <td class="text-end tabular-nums">{{ number_format($margin, 1) }}%</td><td class="text-end tabular-nums">{{ number_format($share, 1) }}%</td><td class="text-end">{{ $ranked->search(fn ($x) => $x['id'] === $r['id']) + 1 }}</td></tr>
                @endforeach
                </tbody>
                <tfoot><tr class="font-semibold"><td class="py-2">{{ __('erp_central.subtotal', ['c' => $cur]) }}</td>@foreach (['revenue', 'expenses', 'net'] as $k)<td class="text-end tabular-nums">{{ $n($g['totals'][$k]) }}</td>@endforeach<td colspan="3"></td></tr></tfoot>
            </table></div>
            @include('central.erp._converted')
        </x-card>
    @empty<x-card>{{ __('erp_central.empty') }}</x-card>@endforelse
    <p class="text-xs text-amber-700">{{ __('erp_central.no_mixing') }}</p>
</x-layouts.admin>
