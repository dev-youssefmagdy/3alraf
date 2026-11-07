<x-layouts.admin :title="__('erp.cards.owners')[0]">
    @include('admin.erp._flash')
    @php $n = fn ($v) => number_format((float) $v, 2); $in = 'mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm'; $tok = fn () => \Illuminate\Support\Str::uuid(); @endphp
    @can('erp.owners.manage')
    <div class="mb-5 grid gap-4 lg:grid-cols-2">
        <x-card :title="__('erp_ext.own.add')">
            <form method="POST" action="{{ route('admin.erp.owners.store') }}" class="grid gap-3 sm:grid-cols-2">@csrf
                <label class="text-sm">{{ __('erp_ext.f.name') }}<input name="name" required maxlength="120" value="{{ old('name') }}" class="{{ $in }}"></label>
                <label class="text-sm">{{ __('erp_ext.f.phone') }}<input name="phone" maxlength="32" class="{{ $in }}"></label>
                <label class="text-sm">{{ __('erp_ext.own.percent') }}<input name="ownership_percent" type="number" step="0.0001" min="0" max="100" required value="{{ old('ownership_percent') }}" class="{{ $in }}"></label>
                <label class="text-sm">{{ __('erp_ext.own.user') }}<select name="admin_id" class="{{ $in }}"><option value="">-</option>@foreach ($users as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select></label>
                <label class="text-sm sm:col-span-2"><input type="checkbox" name="is_eligible" value="1" checked> {{ __('erp_ext.own.eligible') }}</label>
                <div class="text-xs text-slate-500 sm:col-span-2">{{ __('erp_ext.own.percent_total') }}: <b>{{ rtrim(rtrim(number_format((float) $percent, 4), '0'), '.') }}%</b></div>
                <div class="sm:col-span-2"><x-button type="submit">{{ __('erp_ext.f.save') }}</x-button></div></form>
        </x-card>
        <x-card :title="__('erp_ext.own.branch_title')">
            <form method="POST" action="{{ route('admin.erp.owners.allocate') }}" class="grid gap-3 sm:grid-cols-2">@csrf<input type="hidden" name="ref" value="{{ $tok() }}">
                <label class="text-sm">{{ __('erp_ext.f.branch') }}<select name="branch_id" required class="{{ $in }}">@foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('erp_acc.alloc_from') }}<select name="from_branch_id" class="{{ $in }}"><option value="">{{ __('erp_acc.head_office') }}</option>@foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('erp_ext.f.account') }}<select name="account" class="{{ $in }}">@foreach ($cashRoles as $c)<option value="{{ $c }}">{{ __('erp_acc.roles.'.$c) }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('erp_ext.f.amount') }}<input name="amount" type="number" step="0.01" min="0.01" required class="{{ $in }}"></label>
                <label class="text-sm">{{ __('erp_ext.f.date') }}<input name="date" type="date" value="{{ now()->toDateString() }}" required class="{{ $in }}"></label>
                <label class="text-sm">{{ __('erp_ext.f.note') }}<input name="note" maxlength="300" class="{{ $in }}"></label>
                <p class="text-xs text-slate-500 sm:col-span-2">{{ __('erp_acc.alloc_hint') }}</p>
                <div class="sm:col-span-2"><x-button type="submit">{{ __('erp_ext.f.save') }}</x-button></div></form>
        </x-card>
    </div>
    @endcan
    <x-card :title="__('erp_ext.own.owners')" class="mb-5">
        <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp_ext.f.name') }}</th><th class="text-end">{{ __('erp_ext.own.percent') }}</th><th class="text-end">{{ __('erp_ext.own.capital') }}</th><th class="text-end">{{ __('erp_ext.own.drawings') }}</th><th class="text-end">{{ __('erp_ext.own.distributions') }}</th><th class="text-end">{{ __('erp_ext.own.equity') }}</th><th></th></tr></thead><tbody>
        @forelse ($owners as $o)@php $e = $equity[$o->id] ?? null; @endphp
            <tr class="border-b border-slate-100"><td class="py-1.5">{{ $o->name }} @unless ($o->is_eligible)<span class="text-xs text-slate-400">({{ __('erp_ext.f.none') }})</span>@endunless</td><td class="text-end tabular-nums">{{ rtrim(rtrim(number_format((float) $o->ownership_percent, 4), '0'), '.') }}%</td>
                <td class="text-end tabular-nums">{{ $n($e['capital'] ?? 0) }}</td><td class="text-end tabular-nums">{{ $n($e['drawings'] ?? 0) }}</td><td class="text-end tabular-nums">{{ $n($e['distributions'] ?? 0) }}</td><td class="text-end font-semibold tabular-nums">{{ $n($e['equity'] ?? 0) }}</td><td class="text-end"><a class="text-brand-600" href="{{ route('admin.erp.owners.show', $o->id) }}">{{ __('erp_ext.f.view') }}</a></td></tr>
        @empty<tr><td colspan="7" class="py-4 text-slate-500">{{ __('erp_ext.f.none') }}</td></tr>@endforelse
        </tbody></table></div>
    </x-card>
    @if ($recent->isNotEmpty())
    <x-card :title="__('erp_ext.own.recent')"><table class="w-full text-sm"><tbody>@foreach ($recent as $t)<tr class="border-b border-slate-100"><td class="py-1.5">{{ $t->txn_date->format('Y-m-d') }}</td><td>{{ $t->owner->name }}</td><td>{{ __('erp_ext.own.t.'.$t->type) }}</td><td class="text-end tabular-nums">{{ $n($t->amount) }}</td></tr>@endforeach</tbody></table></x-card>
    @endif
</x-layouts.admin>
