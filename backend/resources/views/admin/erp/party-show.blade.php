<x-layouts.admin :title="$party->name">
    <x-slot:header><x-ui.page-header :title="$party->name" /></x-slot:header>
    @include('admin.erp._flash')
    @php $n = fn ($v) => number_format((float) $v, 2); $can = auth('admin')->user()->can('erp.journals.create'); $in = 'form-control mt-1'; $tok = fn () => \Illuminate\Support\Str::uuid(); $r = fn ($k) => route('admin.erp.parties.'.$k, [$type, $party->id]); @endphp
    <div class="mb-4 flex flex-wrap items-center gap-4 text-sm">
        <a class="text-panel-accent-text" href="{{ route('admin.erp.parties.index', $type) }}">&larr; {{ __('erp_ext.party.back') }}</a>
        <span>{{ __('erp_ext.party.outstanding_'.$type) }}: <b class="tabular-nums">{{ $n($pos['outstanding']) }}</b></span>
        <span>{{ __('erp_ext.party.advance') }}: <b class="tabular-nums">{{ $n($pos['advance']) }}</b></span>
        <span>{{ __('erp_ext.party.net') }}: <b class="tabular-nums">{{ $n($pos['balance']) }}</b></span>
    </div>
    @if ($can)
    <div class="mb-5 grid gap-4 lg:grid-cols-2">
        <x-ui.card :title="$type === 'supplier' ? __('erp_ext.party.pay_supplier') : __('erp_ext.party.receive_customer')">
            <form method="POST" action="{{ $r('settle') }}" class="grid gap-3 sm:grid-cols-2">@csrf<input type="hidden" name="ref" value="{{ $tok() }}">
                <label class="text-sm">{{ __('erp_ext.f.amount') }}<input name="amount" type="number" step="0.01" min="0.01" required class="{{ $in }}"></label>
                <label class="text-sm">{{ __('erp_ext.f.account') }}<select name="account" class="{{ $in }}">@foreach ($cashRoles as $c)<option value="{{ $c }}">{{ __('erp_ext.roles.'.$c) }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('erp_ext.f.date') }}<input name="date" type="date" value="{{ now()->toDateString() }}" class="{{ $in }}"></label>
                <label class="text-sm">{{ __('erp_ext.f.branch') }}<select name="branch_id" class="{{ $in }}"><option value="">-</option>@foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
                <label class="text-sm sm:col-span-2"><input type="checkbox" name="advance_only" value="1"> {{ __('erp_ext.party.advance_only') }}</label>
                <p class="text-xs text-panel-text-subtle sm:col-span-2">{{ __('erp_ext.party.pay_hint') }}</p>
                <div class="sm:col-span-2"><x-ui.button type="submit">{{ __('erp_ext.f.save') }}</x-ui.button></div></form>
            @if ((float) $pos['advance'] > 0 && (float) $pos['outstanding'] > 0)
            <form method="POST" action="{{ $r('apply-advance') }}" class="mt-3 border-t border-panel-border pt-3">@csrf<input type="hidden" name="ref" value="{{ $tok() }}"><x-ui.button type="submit" variant="secondary">{{ __('erp_ext.party.apply_advance') }}</x-ui.button></form>
            @endif
        </x-ui.card>
        <x-ui.card :title="__('erp_ext.party.note_title')">
            <form method="POST" action="{{ $r('note') }}" class="grid gap-3 sm:grid-cols-2">@csrf<input type="hidden" name="ref" value="{{ $tok() }}">
                <label class="text-sm">{{ __('erp_ext.f.status') }}<select name="kind" class="{{ $in }}"><option value="credit_note">{{ __('erp_ext.party.credit_note') }}</option><option value="debit_note">{{ __('erp_ext.party.debit_note') }}</option></select></label>
                <label class="text-sm">{{ __('erp_ext.f.amount') }}<input name="amount" type="number" step="0.01" min="0.01" required class="{{ $in }}"></label>
                <label class="text-sm sm:col-span-2">{{ __('erp_ext.party.counter') }}<select name="account_id" class="{{ $in }}"><option value="">-</option>@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} {{ $a->name }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('erp_ext.f.date') }}<input name="date" type="date" value="{{ now()->toDateString() }}" class="{{ $in }}"></label>
                <label class="text-sm">{{ __('erp_ext.f.note') }}<input name="description" maxlength="300" class="{{ $in }}"></label>
                <div class="sm:col-span-2"><x-ui.button type="submit">{{ __('erp_ext.f.save') }}</x-ui.button></div></form>
            <form method="POST" action="{{ $r('opening') }}" class="mt-3 grid gap-3 border-t border-panel-border pt-3 sm:grid-cols-3">@csrf<input type="hidden" name="ref" value="{{ $tok() }}">
                <div class="text-sm font-semibold sm:col-span-3">{{ __('erp_ext.party.opening_title') }} <span class="font-normal text-panel-text-subtle">{{ __('erp_ext.party.opening_hint_'.$type) }}</span></div>
                <input name="amount" type="number" step="0.01" min="0.01" required class="{{ $in }}" placeholder="{{ __('erp_ext.f.amount') }}"><input name="date" type="date" value="{{ now()->toDateString() }}" class="{{ $in }}"><x-ui.button type="submit" variant="secondary">{{ __('erp_ext.f.save') }}</x-ui.button></form>
        </x-ui.card>
    </div>
    @endif
    <x-ui.card :title="__('erp_ext.party.statement')">
        <form method="GET" class="mb-3 flex flex-wrap items-end gap-3 text-sm"><label>{{ __('erp_ext.f.from') }}<input type="date" name="from" value="{{ $from }}" class="form-control mt-1 !w-auto"></label><label>{{ __('erp_ext.f.to') }}<input type="date" name="to" value="{{ $to }}" class="form-control mt-1 !w-auto"></label><x-ui.button type="submit" variant="secondary">{{ __('erp_ext.f.filter') }}</x-ui.button></form>
        <x-ui.table><thead><tr><th>{{ __('erp_ext.f.date') }}</th><th>{{ __('erp_ext.f.number') }}</th><th>{{ __('erp_ext.f.note') }}</th><th class="text-end">{{ __('erp_ext.f.debit') }}</th><th class="text-end">{{ __('erp_ext.f.credit') }}</th><th class="text-end">{{ __('erp_ext.f.balance') }}</th></tr></thead><tbody>
        <tr class="bg-panel-surface-sunken"><td colspan="5">{{ __('erp_ext.party.opening') }}</td><td data-label="{{ __('erp_ext.f.number') }}" class="text-end tabular-nums">{{ $n($st['opening']) }}</td></tr>
        @foreach ($st['rows'] as $x)<tr><td data-label="">{{ $x['line']->entry->entry_date->format('Y-m-d') }}</td><td data-label="{{ __('erp_ext.f.number') }}">@include('admin.erp._jlink', ['id' => $x['line']->entry_id, 'no' => $x['line']->entry->number])</td><td data-label="{{ __('erp_ext.f.note') }}">{{ $x['line']->memo ?: $x['line']->entry->description }}</td><td data-label="{{ __('erp_ext.f.debit') }}" class="text-end tabular-nums">{{ (float) $x['line']->debit ? $n($x['line']->debit) : '' }}</td><td data-label="{{ __('erp_ext.f.credit') }}" class="text-end tabular-nums">{{ (float) $x['line']->credit ? $n($x['line']->credit) : '' }}</td><td data-label="{{ __('erp_ext.f.balance') }}" class="text-end tabular-nums">{{ $n($x['balance']) }}</td></tr>@endforeach
        <tr class="font-semibold"><td colspan="5">{{ __('erp_ext.party.closing') }}</td><td data-label="{{ __('erp_ext.f.number') }}" class="text-end tabular-nums">{{ $n($st['closing']) }}</td></tr></tbody></x-ui.table>
    </x-ui.card>
</x-layouts.admin>
