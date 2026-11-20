<x-layouts.admin :title="__('erp_ext.party.ageing').' - '.__('erp_ext.party.'.$type)">
    <x-slot:header><x-ui.page-header :title="__('erp_ext.party.ageing').' - '.__('erp_ext.party.'.$type)" /></x-slot:header>
    @php $n = fn ($v) => number_format((float) $v, 2); $b = __('erp_ext.party.buckets'); @endphp
    <x-ui.card class="mb-5">
        <form method="GET" class="flex items-end gap-3 text-sm"><label>{{ __('erp_ext.f.as_of') }}<input type="date" name="as_of" value="{{ $data['as_of'] }}" class="form-control mt-1 !w-auto"></label><x-ui.button type="submit" variant="secondary">{{ __('erp_ext.f.filter') }}</x-ui.button>
            <a class="btn-sm btn-secondary" href="{{ route('admin.erp.parties.index', $type) }}">{{ __('erp_ext.party.back') }}</a></form>
    </x-ui.card>
    <x-ui.card>
        <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b text-panel-text-subtle"><th class="py-2 text-start">{{ __('erp_ext.f.name') }}</th>@foreach (['b0', 'b31', 'b61', 'b90', 'total', 'credit'] as $k)<th class="text-end">{{ $b[$k] }}</th>@endforeach</tr></thead><tbody>
        @forelse ($data['rows'] as $r)
            <tr class="border-b border-panel-border"><td class="py-1.5">@if ($r['unattributed']){{ __('erp_ext.f.unattributed') }}@else<a class="text-panel-accent-text" href="{{ route('admin.erp.parties.show', [$type, $r['id']]) }}">{{ $r['name'] }}</a>@endif</td>@foreach (['b0', 'b31', 'b61', 'b90', 'total', 'credit'] as $k)<td class="text-end tabular-nums">{{ (float) $r[$k] ? $n($r[$k]) : '' }}</td>@endforeach</tr>
        @empty<tr><td colspan="7" class="py-4 text-panel-text-subtle">{{ __('erp_ext.f.none') }}</td></tr>@endforelse
        </tbody><tfoot><tr class="font-semibold"><td class="py-2">{{ __('erp_ext.f.total') }}</td>@foreach (['b0', 'b31', 'b61', 'b90', 'total', 'credit'] as $k)<td class="text-end tabular-nums">{{ $n($data['totals'][$k]) }}</td>@endforeach</tr></tfoot></table></div>
    </x-ui.card>
</x-layouts.admin>
