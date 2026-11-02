<x-layouts.admin guard="central" :title="__('erp_central.cards.periods')[0]">
    @include('central.erp._back')
    @php $in = 'mt-1 block rounded border border-slate-300 px-2 py-1.5'; @endphp
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <label>{{ __('erp_central.col.tenant') }}<select name="tenant_id" class="{{ $in }}"><option value="">{{ __('erp_central.f.all') }}</option>@foreach ($allTenants as $t)<option value="{{ $t->id }}" @selected(($q['tenant_id'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach</select></label>
            <x-button type="submit">{{ __('erp_central.f.filter') }}</x-button>
        </form>
    </x-card>
    <x-card class="mb-5" :title="__('erp_central.cards.periods')[0]">
        <table class="w-full text-sm" data-periods><thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp_central.col.tenant') }}</th><th class="text-end">{{ __('erp_central.col.open') }}</th><th class="text-end">{{ __('erp_central.col.closed') }}</th><th class="text-start ps-3">{{ __('erp_central.col.last_closed') }}</th><th class="text-start">{{ __('erp_central.col.last_posting') }}</th></tr></thead>
        <tbody>@foreach ($tenants as $t)@php $o = $overview[$t->id] ?? null; @endphp<tr class="border-b border-slate-100" data-tenant="{{ $t->id }}"><td class="py-1.5"><a class="text-brand-600" href="{{ route('central.erp.periods', ['tenant_id' => $t->id]) }}">{{ $t->name }}</a></td><td class="text-end">{{ $o['periods_open'] ?? 0 }}</td><td class="text-end">{{ $o['periods_closed'] ?? 0 }}</td><td class="ps-3">{{ $o['last_closed'] ?? '-' }}</td><td>{{ $o['last_posting'] ?? '-' }}</td></tr>@endforeach</tbody></table>
    </x-card>
    @if (! empty($q['tenant_id']))
        <x-card class="mb-5" :title="__('erp_central.period_list')">
            <table class="w-full text-sm"><tbody>@foreach ($periods->flatten(1) as $p)<tr class="border-b border-slate-100"><td class="py-1">{{ $p->name }}</td><td>{{ $p->year_name }}</td><td>{{ \Illuminate\Support\Str::of($p->starts_on)->substr(0, 10) }} - {{ \Illuminate\Support\Str::of($p->ends_on)->substr(0, 10) }}</td><td>{{ __('erp_central.period_status.'.$p->status) }}</td><td>{{ $p->closed_at }}</td></tr>@endforeach</tbody></table>
        </x-card>
    @endif
    <x-card :title="__('erp_central.reopen_history')">
        <table class="w-full text-sm" data-reopen><thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp_central.col.date') }}</th><th class="text-start">{{ __('erp_central.col.tenant') }}</th><th class="text-start">{{ __('erp_central.col.action') }}</th><th class="text-start">{{ __('erp_central.col.detail') }}</th></tr></thead>
        <tbody>@forelse ($reopen as $l)@php $m = json_decode((string) $l->meta, true) ?: []; @endphp<tr class="border-b border-slate-100"><td class="py-1">{{ $l->created_at }}</td><td>{{ $tenants->firstWhere('id', $l->tenant_id)->name ?? '#'.$l->tenant_id }}</td><td>{{ $l->action }}</td><td>{{ ($m['name'] ?? '').' - '.($m['reason'] ?? '') }}</td></tr>@empty<tr><td colspan="4" class="py-3 text-slate-500">{{ __('erp_central.empty') }}</td></tr>@endforelse</tbody></table>
    </x-card>
</x-layouts.admin>
