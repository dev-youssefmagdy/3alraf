<x-layouts.admin guard="central" :title="__('erp_central.cards.transactions')[0]">
    @include('central.erp._back')
    @php $in = 'mt-1 block rounded border border-slate-300 px-2 py-1.5'; $n = fn ($v) => number_format((float) $v, 2); @endphp
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <label>{{ __('erp_central.col.tenant') }}<select name="tenant_id" class="{{ $in }}"><option value="">{{ __('erp_central.f.all') }}</option>@foreach ($tenants as $t)<option value="{{ $t->id }}" @selected(($q['tenant_id'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach</select></label>
            <label>{{ __('erp_central.f.branch') }}<select name="branch_id" class="{{ $in }}"><option value="">{{ __('erp_central.f.all') }}</option>@foreach ($branches as $b)<option value="{{ $b->id }}" @selected(($q['branch_id'] ?? '') == $b->id)>{{ $b->name }} (#{{ $b->tenant_id }})</option>@endforeach</select></label>
            <label>{{ __('erp_central.f.account') }}<input name="account" value="{{ $q['account'] ?? '' }}" class="{{ $in }} w-24"></label>
            <label>{{ __('erp_central.f.from') }}<input type="date" name="from" value="{{ $q['from'] ?? '' }}" class="{{ $in }}"></label>
            <label>{{ __('erp_central.f.to') }}<input type="date" name="to" value="{{ $q['to'] ?? '' }}" class="{{ $in }}"></label>
            <label>{{ __('erp_central.col.status') }}<select name="status" class="{{ $in }}"><option value="">{{ __('erp_central.f.all') }}</option>@foreach (['draft', 'posted', 'reversed'] as $s)<option @selected(($q['status'] ?? '') === $s)>{{ $s }}</option>@endforeach</select></label>
            <label>{{ __('erp_central.col.type') }}<select name="type" class="{{ $in }}"><option value="">{{ __('erp_central.f.all') }}</option>@foreach (\App\Services\Erp\Reports\ErpReportFilter::ENTRY_TYPES as $s)<option @selected(($q['type'] ?? '') === $s)>{{ $s }}</option>@endforeach</select></label>
            <label>{{ __('erp_central.col.source') }}<select name="source" class="{{ $in }}"><option value="">{{ __('erp_central.f.all') }}</option>@foreach ($sources as $s)<option @selected(($q['source'] ?? '') === $s)>{{ $s }}</option>@endforeach</select></label>
            <label>{{ __('erp_central.f.search') }}<input name="q" value="{{ $q['q'] ?? '' }}" class="{{ $in }}"></label>
            <x-button type="submit">{{ __('erp_central.f.filter') }}</x-button>
        </form>
    </x-card>
    <x-card>
        <div class="overflow-x-auto"><table class="w-full text-sm" data-entries>
            <thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp_central.col.tenant') }}</th><th class="text-start">{{ __('erp_central.col.number') }}</th><th class="text-start">{{ __('erp_central.col.date') }}</th><th class="text-start">{{ __('erp_central.col.status') }}</th><th class="text-start">{{ __('erp_central.col.type') }}</th><th class="text-start">{{ __('erp_central.col.source') }}</th><th class="text-start">{{ __('erp_central.col.description') }}</th><th class="text-end">{{ __('erp_central.col.total') }}</th></tr></thead>
            <tbody>
            @forelse ($entries as $e)
                <tr class="border-b border-slate-100"><td class="py-1.5">{{ $e->tenant_name }}</td><td><a class="font-mono text-brand-600" href="{{ route('central.erp.transactions.show', $e->id) }}">{{ $e->number }}</a></td><td>{{ \Illuminate\Support\Str::of($e->entry_date)->substr(0, 10) }}</td><td>{{ $e->status }}</td><td>{{ $e->type }}</td><td>{{ $e->source_type ? $e->source_type.' #'.$e->source_id : '-' }}</td><td>{{ \Illuminate\Support\Str::limit($e->description, 60) }}</td><td class="text-end tabular-nums">{{ $n($e->total) }} {{ $e->tenant_currency }}</td></tr>
            @empty<tr><td colspan="8" class="py-4 text-slate-500">{{ __('erp_central.empty') }}</td></tr>@endforelse
            </tbody>
        </table></div>
        <div class="mt-4">{{ $entries->links() }}</div>
    </x-card>
</x-layouts.admin>
