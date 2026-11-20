<x-layouts.admin :title="__('erp_dist.report')">
    <x-slot:header><x-ui.page-header :title="__('erp_dist.report')" /></x-slot:header>
    @php $n = fn ($v) => number_format((float) $v, 2); @endphp
    <x-ui.card>
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 text-sm"><label>{{ __('erp.f.from') }}<input type="date" name="from" value="{{ $from }}" class="form-control mt-1 !w-auto"></label><label>{{ __('erp.f.to') }}<input type="date" name="to" value="{{ $to }}" class="form-control mt-1 !w-auto"></label><x-ui.button type="submit" variant="secondary">{{ __('erp.f.filter') }}</x-ui.button></form>
        <x-ui.table><thead><tr><th>{{ __('erp_ext.f.name') }}</th><th class="text-end">{{ __('erp_dist.accrued') }}</th><th class="text-end">{{ __('erp_dist.paid_flag') }}</th><th class="text-end">{{ __('erp_dist.outstanding') }}</th><th></th></tr></thead><tbody>
        @forelse ($rows as $r)<tr><td data-label="">{{ $r['owner']->name }}</td><td data-label="{{ __('erp_dist.accrued') }}" class="text-end tabular-nums">{{ $n($r['accrued']) }}</td><td data-label="{{ __('erp_dist.paid_flag') }}" class="text-end tabular-nums">{{ $n($r['paid']) }}</td><td data-label="{{ __('erp_dist.outstanding') }}" class="text-end tabular-nums">{{ $n($r['outstanding']) }}</td><td class="ui-table-actions text-end"><a class="text-panel-accent-text" href="{{ route('admin.erp.profits.statement', $r['owner']->id) }}">{{ __('erp_dist.statement') }}</a></td></tr>
        @empty<tr><td colspan="5">{{ __('erp_ext.f.none') }}</td></tr>@endforelse</tbody></x-ui.table>
    </x-ui.card>
</x-layouts.admin>
