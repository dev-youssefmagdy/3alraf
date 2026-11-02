<x-layouts.admin guard="tenant" :title="__('erp_dist.run').' #'.$d->id">
    @include('tenant.erp._flash')
    @php $n = fn ($v) => number_format((float) $v, 2); $in = 'rounded border border-slate-300 px-2 py-1 text-xs'; @endphp
    <div class="mb-4 flex flex-wrap items-center gap-4 text-sm"><a class="text-brand-600" href="{{ route('tenant.erp.profits.index') }}">&larr; {{ __('erp.cards.distribution')[0] }}</a>
        <span>{{ $d->period_start->format('Y-m-d') }} → {{ $d->period_end->format('Y-m-d') }}</span><span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs">{{ __('erp_dist.status.'.$d->status) }}</span>
        @if ($d->entry)<a class="font-mono text-brand-600" href="{{ route('tenant.erp.journals.show', $d->journal_entry_id) }}">{{ $d->entry->number }}</a>@endif</div>
    <x-card :title="__('erp_dist.summary')" class="mb-5"><table class="w-full text-sm"><tbody>
        @foreach (['ledger_profit' => 'ledger_profit', 'tax_amount' => 'tax', 'net_profit' => 'net_profit', 'retained_before' => 'prior_retained', 'reserve_amount' => 'reserve', 'distributable_profit' => 'distributable_profit', 'cash_available' => 'distributable_cash', 'distributable' => 'distributable', 'distributed_amount' => 'distributed', 'retained_after' => 'retained_after'] as $col => $l)
            <tr class="border-b border-slate-100"><td class="py-1.5">{{ __('erp_dist.l.'.$l) }}</td><td class="text-end tabular-nums">{{ $n($d->$col) }}</td></tr>@endforeach
        <tr><td class="py-1.5 text-slate-500">{{ __('erp_dist.created_by') }} / {{ __('erp_dist.approved_by') }}</td><td class="text-end">{{ $d->creator?->name ?? '-' }} / {{ $d->approver?->name ?? '-' }}</td></tr></tbody></table></x-card>
    <x-card :title="__('erp_dist.shares')" class="mb-5"><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp_ext.f.name') }}</th><th class="text-end">%</th><th class="text-end">{{ __('erp_ext.f.amount') }}</th><th class="text-start">{{ __('erp_ext.f.status') }}</th><th></th></tr></thead><tbody>
        @foreach ($d->lines as $l)<tr class="border-b border-slate-100"><td class="py-1.5">{{ $l->owner->name }}</td><td class="text-end tabular-nums">{{ $n($l->percent) }}</td><td class="text-end tabular-nums">{{ $n($l->amount) }}</td>
            <td>{{ $l->paid ? __('erp_dist.paid_flag').' '.$l->paid_at?->format('Y-m-d') : __('erp_dist.unpaid') }}</td>
            <td class="text-end">@if ($d->status === 'posted' && ! $l->paid && $canDistribute)<form method="POST" action="{{ route('tenant.erp.profits.pay', [$d->id, $l->id]) }}" class="inline-flex gap-2">@csrf<select name="account" class="{{ $in }}">@foreach ($cashRoles as $c)<option value="{{ $c }}">{{ __('erp_ext.roles.'.$c) }}</option>@endforeach</select><x-button type="submit" variant="secondary">{{ __('erp_dist.pay') }}</x-button></form>@endif
                <a class="ms-2 text-brand-600" href="{{ route('tenant.erp.profits.statement', $l->owner_id) }}?distribution={{ $d->id }}">{{ __('erp_dist.statement') }}</a></td></tr>@endforeach
        </tbody></table></div></x-card>
    @if ($canDistribute && in_array($d->status, ['draft', 'approved', 'posted']))
    <div class="flex flex-wrap gap-3">
        @if ($d->status === 'draft')<form method="POST" action="{{ route('tenant.erp.profits.approve', $d->id) }}">@csrf<x-button type="submit">{{ __('erp_dist.approve') }}</x-button></form>@if ($second)<span class="self-center text-xs text-slate-500">{{ __('erp_dist.second_hint') }}</span>@endif @endif
        @if ($d->status === 'approved')<form method="POST" action="{{ route('tenant.erp.profits.post', $d->id) }}">@csrf<x-button type="submit">{{ __('erp_dist.post') }}</x-button></form>@endif
        <form method="POST" action="{{ route('tenant.erp.profits.cancel', $d->id) }}" class="inline-flex gap-2">@csrf<input name="reason" placeholder="{{ __('erp.f.reason') }}" class="{{ $in }}"><x-button type="submit" variant="danger">{{ __('erp_dist.cancel') }}</x-button></form>
    </div>
    @endif
</x-layouts.admin>
