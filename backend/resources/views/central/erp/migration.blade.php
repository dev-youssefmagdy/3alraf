<x-layouts.admin guard="central" :title="__('erp_central.cards.migration')[0]">
    @include('central.erp._back')
    @php $in = 'mt-1 block rounded border border-slate-300 px-2 py-1.5'; @endphp
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <label>{{ __('erp_central.col.tenant') }}<select name="tenant_id" class="{{ $in }}"><option value="">{{ __('erp_central.f.all') }}</option>@foreach ($tenants as $t)<option value="{{ $t->id }}" @selected(($q['tenant_id'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach</select></label>
            <label>{{ __('erp.bf.status') }}<select name="status" class="{{ $in }}"><option value="">{{ __('erp_central.f.all') }}</option>@foreach (['running', 'completed', 'partial', 'failed'] as $st)<option @selected(($q['status'] ?? '') === $st)>{{ $st }}</option>@endforeach</select></label>
            <x-button type="submit">{{ __('erp_central.f.filter') }}</x-button>
        </form>
    </x-card>
    <x-card>
        <div class="overflow-x-auto"><table class="w-full text-sm" data-runs>
            <thead><tr class="border-b text-slate-500"><th class="py-2 text-start">#</th><th class="text-start">{{ __('erp_central.col.tenant') }}</th><th class="text-start">{{ __('erp.bf.from') }}</th><th class="text-start">{{ __('erp.bf.status') }}</th><th class="text-start">{{ __('erp.bf.started') }}</th><th class="text-start">{{ __('erp.bf.by') }}</th><th class="text-end">{{ __('erp.bf.failed_docs') }}</th></tr></thead>
            <tbody>@forelse ($runs as $run)<tr class="border-b border-slate-100"><td class="py-1.5">{{ $run->id }}</td><td>{{ $run->tenant_name }}</td><td>{{ substr((string) $run->from_date, 0, 10) }}</td><td>{{ $run->status }}</td><td>{{ $run->started_at }}</td><td>{{ $run->triggered_by }}</td><td class="text-end">{{ $failed[$run->tenant_id] ?? 0 }}</td></tr>@empty<tr><td colspan="7" class="py-4 text-slate-500">{{ __('erp.bf.no_runs') }}</td></tr>@endforelse</tbody>
        </table></div>
        <div class="mt-4">{{ $runs->links() }}</div>
    </x-card>
</x-layouts.admin>
