<x-layouts.admin guard="tenant" :title="$owner->name">
    @include('tenant.erp._flash')
    @php $n = fn ($v) => number_format((float) $v, 2); $in = 'mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm'; @endphp
    <div class="mb-4 flex flex-wrap items-center gap-4 text-sm">
        <a class="text-brand-600" href="{{ route('tenant.erp.owners.index') }}">&larr; {{ __('erp_ext.own.owners') }}</a>
        <span>{{ __('erp_ext.own.percent') }}: <b>{{ rtrim(rtrim(number_format((float) $owner->ownership_percent, 4), '0'), '.') }}%</b></span>
        <span>{{ __('erp_ext.own.equity') }}: <b class="tabular-nums">{{ $n($equity['equity'] ?? 0) }}</b></span>
    </div>
    @can('erp.owners.manage')
    <div class="mb-5 grid gap-4 lg:grid-cols-2">
        <x-card :title="__('erp_ext.own.txn')">
            <form method="POST" action="{{ route('tenant.erp.owners.transact', $owner->id) }}" class="grid gap-3 sm:grid-cols-2">@csrf<input type="hidden" name="ref" value="{{ \Illuminate\Support\Str::uuid() }}">
                <label class="text-sm">{{ __('erp_ext.own.type') }}<select name="type" class="{{ $in }}">@foreach (\App\Models\Erp\OwnerTransaction::NEW_TYPES as $t)<option value="{{ $t }}">{{ __('erp_ext.own.t.'.$t) }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('erp_ext.f.amount') }}<input name="amount" type="number" step="0.01" min="0.01" required class="{{ $in }}"></label>
                <label class="text-sm">{{ __('erp_ext.own.cash_account') }}<select name="account" class="{{ $in }}">@foreach ($cashRoles as $c)<option value="{{ $c }}">{{ __('erp_ext.roles.'.$c) }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('erp_ext.f.branch') }}<select name="branch_id" class="{{ $in }}"><option value="">-</option>@foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('erp_ext.f.date') }}<input name="date" type="date" value="{{ now()->toDateString() }}" required class="{{ $in }}"></label>
                <label class="text-sm">{{ __('erp_ext.f.note') }}<input name="note" maxlength="300" class="{{ $in }}"></label>
                <div class="sm:col-span-2"><x-button type="submit">{{ __('erp_ext.f.save') }}</x-button></div></form>
        </x-card>
        <x-card :title="__('erp_ext.own.edit')">
            <form method="POST" action="{{ route('tenant.erp.owners.update', $owner->id) }}" class="grid gap-3 sm:grid-cols-2">@csrf @method('PUT')
                <label class="text-sm">{{ __('erp_ext.f.name') }}<input name="name" required value="{{ $owner->name }}" class="{{ $in }}"></label>
                <label class="text-sm">{{ __('erp_ext.f.phone') }}<input name="phone" value="{{ $owner->phone }}" class="{{ $in }}"></label>
                <label class="text-sm">{{ __('erp_ext.own.percent') }}<input name="ownership_percent" type="number" step="0.0001" min="0" max="100" required value="{{ $owner->ownership_percent }}" class="{{ $in }}"></label>
                <label class="text-sm self-end"><input type="checkbox" name="is_eligible" value="1" @checked($owner->is_eligible)> {{ __('erp_ext.own.eligible') }}</label>
                <input type="hidden" name="tenant_admin_id" value="{{ $owner->tenant_admin_id }}">
                <div class="sm:col-span-2"><x-button type="submit" variant="secondary">{{ __('erp_ext.f.save') }}</x-button></div></form>
        </x-card>
    </div>
    @endcan
    @if ($dists->isNotEmpty())
    <x-card :title="__('erp_dist.owner_dists')" class="mb-5"><div class="overflow-x-auto"><table class="w-full text-sm"><tbody>
        @foreach ($dists as $l)<tr class="border-b border-slate-100"><td class="py-1.5">#{{ $l->distribution_id }} {{ $l->distribution->period_start->format('Y-m-d') }} → {{ $l->distribution->period_end->format('Y-m-d') }}</td><td class="text-end tabular-nums">{{ $n($l->amount) }}</td><td>{{ __('erp_dist.status.'.$l->distribution->status) }} {{ $l->paid ? '/ '.__('erp_dist.paid_flag') : '' }}</td></tr>@endforeach
        </tbody></table></div><a class="mt-2 inline-block text-sm text-brand-600" href="{{ route('tenant.erp.profits.statement', $owner->id) }}">{{ __('erp_dist.statement') }}</a></x-card>
    @endif
    <x-card :title="__('erp_ext.own.statement')">
        <form method="GET" class="mb-3 flex flex-wrap items-end gap-3 text-sm"><label>{{ __('erp_ext.f.from') }}<input type="date" name="from" value="{{ $from }}" class="mt-1 block rounded border border-slate-300 px-2 py-1.5"></label><label>{{ __('erp_ext.f.to') }}<input type="date" name="to" value="{{ $to }}" class="mt-1 block rounded border border-slate-300 px-2 py-1.5"></label><x-button type="submit" variant="secondary">{{ __('erp_ext.f.filter') }}</x-button></form>
        <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp_ext.f.date') }}</th><th class="text-start">{{ __('erp_ext.f.number') }}</th><th class="text-start">{{ __('erp_ext.f.account') }}</th><th class="text-end">{{ __('erp_ext.f.debit') }}</th><th class="text-end">{{ __('erp_ext.f.credit') }}</th><th class="text-end">{{ __('erp_ext.f.balance') }}</th></tr></thead><tbody>
        <tr class="bg-slate-50"><td colspan="5" class="py-1.5">{{ __('erp_ext.party.opening') }}</td><td class="text-end tabular-nums">{{ $n($st['opening']) }}</td></tr>
        @foreach ($st['rows'] as $x)<tr class="border-b border-slate-100"><td class="py-1.5">{{ $x['line']->entry->entry_date->format('Y-m-d') }}</td><td>@include('tenant.erp._jlink', ['id' => $x['line']->entry_id, 'no' => $x['line']->entry->number])</td><td>{{ $x['line']->account->name }}</td><td class="text-end tabular-nums">{{ (float) $x['line']->debit ? $n($x['line']->debit) : '' }}</td><td class="text-end tabular-nums">{{ (float) $x['line']->credit ? $n($x['line']->credit) : '' }}</td><td class="text-end tabular-nums">{{ $n($x['balance']) }}</td></tr>@endforeach
        <tr class="font-semibold"><td colspan="5" class="py-2">{{ __('erp_ext.party.closing') }}</td><td class="text-end tabular-nums">{{ $n($st['closing']) }}</td></tr></tbody></table></div>
    </x-card>
</x-layouts.admin>
