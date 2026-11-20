<x-layouts.admin :title="__('erp_dist.run').' #'.$d->id">
    <x-slot:header><x-ui.page-header :title="__('erp_dist.run').' #'.$d->id" /></x-slot:header>
    @include('admin.erp._flash')
    @php $n = fn ($v) => number_format((float) $v, 2); $in = 'form-control !w-auto !py-1 text-xs'; @endphp
    <div class="mb-4 flex flex-wrap items-center gap-4 text-sm"><a class="text-panel-accent-text" href="{{ route('admin.erp.profits.index') }}">&larr; {{ __('erp.cards.distribution')[0] }}</a>
        <span>{{ $d->period_start->format('Y-m-d') }} → {{ $d->period_end->format('Y-m-d') }}</span><span class="rounded-full bg-panel-border px-2 py-0.5 text-xs">{{ __('erp_dist.status.'.$d->status) }}</span>
        @if ($d->entry)@include('admin.erp._jlink', ['id' => $d->journal_entry_id, 'no' => $d->entry->number])@endif</div>
    <x-ui.card :title="__('erp_dist.summary')" class="mb-5"><x-ui.table :mobile="false"><tbody>
        @foreach (['ledger_profit' => 'ledger_profit', 'tax_amount' => 'tax', 'net_profit' => 'net_profit', 'retained_before' => 'prior_retained', 'reserve_amount' => 'reserve', 'distributable_profit' => 'distributable_profit', 'cash_available' => 'distributable_cash', 'distributable' => 'distributable', 'distributed_amount' => 'distributed', 'retained_after' => 'retained_after'] as $col => $l)
            <tr><td>{{ __('erp_dist.l.'.$l) }}</td><td class="text-end tabular-nums">{{ $n($d->$col) }}</td></tr>@endforeach
        <tr><td>{{ __('erp_dist.created_by') }} / {{ __('erp_dist.approved_by') }}</td><td class="text-end">{{ $d->creator?->name ?? '-' }} / {{ $d->approver?->name ?? '-' }}</td></tr></tbody></x-ui.table></x-ui.card>
    <x-ui.card :title="__('erp_dist.shares')" class="mb-5"><x-ui.table><thead><tr><th>{{ __('erp_ext.f.name') }}</th><th class="text-end">%</th><th class="text-end">{{ __('erp_ext.f.amount') }}</th><th>{{ __('erp_ext.f.status') }}</th><th></th></tr></thead><tbody>
        @foreach ($d->lines as $l)<tr><td data-label="">{{ $l->owner->name }}</td><td data-label="%" class="text-end tabular-nums">{{ $n($l->percent) }}</td><td data-label="{{ __('erp_ext.f.amount') }}" class="text-end tabular-nums">{{ $n($l->amount) }}</td>
            <td data-label="{{ __('erp_ext.f.status') }}">{{ $l->paid ? __('erp_dist.paid_flag').' '.$l->paid_at?->format('Y-m-d') : __('erp_dist.unpaid') }}</td>
            <td class="ui-table-actions text-end">@if ($d->status === 'posted' && ! $l->paid && $canDistribute)<form method="POST" action="{{ route('admin.erp.profits.pay', [$d->id, $l->id]) }}" class="inline-flex gap-2">@csrf<select name="account" class="{{ $in }}">@foreach ($cashRoles as $c)<option value="{{ $c }}">{{ __('erp_ext.roles.'.$c) }}</option>@endforeach</select><x-ui.button type="submit" variant="secondary">{{ __('erp_dist.pay') }}</x-ui.button></form>@endif
                <a class="ms-2 text-panel-accent-text" href="{{ route('admin.erp.profits.statement', $l->owner_id) }}?distribution={{ $d->id }}">{{ __('erp_dist.statement') }}</a></td></tr>@endforeach
        </tbody></x-ui.table></x-ui.card>
    @if ($canDistribute && in_array($d->status, ['draft', 'approved', 'posted']))
    <div class="flex flex-wrap gap-3">
        @if ($d->status === 'draft')<form method="POST" action="{{ route('admin.erp.profits.approve', $d->id) }}">@csrf<x-ui.button type="submit">{{ __('erp_dist.approve') }}</x-ui.button></form>@if ($second)<span class="self-center text-xs text-panel-text-subtle">{{ __('erp_dist.second_hint') }}</span>@endif @endif
        @if ($d->status === 'approved')<form method="POST" action="{{ route('admin.erp.profits.post', $d->id) }}">@csrf<x-ui.button type="submit">{{ __('erp_dist.post') }}</x-ui.button></form>@endif
        <form method="POST" action="{{ route('admin.erp.profits.cancel', $d->id) }}" class="inline-flex gap-2">@csrf<input name="reason" placeholder="{{ __('erp.f.reason') }}" class="{{ $in }}"><x-ui.button type="submit" variant="danger">{{ __('erp_dist.cancel') }}</x-ui.button></form>
    </div>
    @endif
</x-layouts.admin>
