<x-layouts.admin guard="tenant" :title="__('erp_ext.party.ageing').' - '.__('erp_ext.party.'.$type)">
    @php $n = fn ($v) => number_format((float) $v, 2); $b = __('erp_ext.party.buckets'); @endphp
    <x-card class="mb-5">
        <form method="GET" class="flex items-end gap-3 text-sm"><label>{{ __('erp_ext.f.as_of') }}<input type="date" name="as_of" value="{{ $data['as_of'] }}" class="mt-1 block rounded border border-slate-300 px-2 py-1.5"></label><x-button type="submit" variant="secondary">{{ __('erp_ext.f.filter') }}</x-button>
            <a class="btn-sm btn-secondary" href="{{ route('tenant.erp.parties.index', $type) }}">{{ __('erp_ext.party.back') }}</a></form>
    </x-card>
    <x-card>
        <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp_ext.f.name') }}</th>@foreach (['b0', 'b31', 'b61', 'b90', 'total', 'credit'] as $k)<th class="text-end">{{ $b[$k] }}</th>@endforeach</tr></thead><tbody>
        @forelse ($data['rows'] as $r)
            <tr class="border-b border-slate-100"><td class="py-1.5">@if ($r['unattributed']){{ __('erp_ext.f.unattributed') }}@else<a class="text-brand-600" href="{{ route('tenant.erp.parties.show', [$type, $r['id']]) }}">{{ $r['name'] }}</a>@endif</td>@foreach (['b0', 'b31', 'b61', 'b90', 'total', 'credit'] as $k)<td class="text-end tabular-nums">{{ (float) $r[$k] ? $n($r[$k]) : '' }}</td>@endforeach</tr>
        @empty<tr><td colspan="7" class="py-4 text-slate-500">{{ __('erp_ext.f.none') }}</td></tr>@endforelse
        </tbody><tfoot><tr class="font-semibold"><td class="py-2">{{ __('erp_ext.f.total') }}</td>@foreach (['b0', 'b31', 'b61', 'b90', 'total', 'credit'] as $k)<td class="text-end tabular-nums">{{ $n($data['totals'][$k]) }}</td>@endforeach</tr></tfoot></table></div>
    </x-card>
</x-layouts.admin>
