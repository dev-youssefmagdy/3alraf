<x-layouts.admin guard="tenant" :title="__('erp.cards.branches')[0]">
    @include('tenant.erp._flash')
    @php $n = fn ($v) => number_format((float) $v, 2); $in = 'mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm'; @endphp
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <label>{{ __('erp.f.from') }}<input type="date" name="from" value="{{ $f->from }}" class="{{ $in }}"></label>
            <label>{{ __('erp.f.to') }}<input type="date" name="to" value="{{ $f->to }}" class="{{ $in }}"></label>
            <x-button type="submit">{{ __('erp.f.filter') }}</x-button>
            @can('erp.reports.view')
                <a class="text-brand-600" href="{{ route('tenant.erp.reports.show', ['report' => 'branch-profitability'] + $f->query()) }}">{{ __('erp_rep.t.branch-profitability') }}</a>
            @endcan
        </form>
    </x-card>
    <x-card class="mb-5"><div class="overflow-x-auto"><table class="w-full text-sm">
        <thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp.f.branch') }}</th><th class="text-end">{{ __('erp_rep.col.revenue') }}</th><th class="text-end">{{ __('erp_rep.col.net') }}</th><th class="text-end">{{ __('erp_rep.bt.cash') }}</th><th class="text-end">{{ __('erp_rep.bt.inventory') }}</th><th class="text-end">{{ __('erp_rep.bt.inv_out') }}</th><th class="text-end">{{ __('erp_rep.bt.inv_in') }}</th><th class="text-end">{{ __('erp_rep.bt.cash_out') }}</th><th class="text-end">{{ __('erp_rep.bt.cash_in') }}</th></tr></thead>
        <tbody>@foreach ($rows as $r)<tr class="border-b border-slate-100"><td class="py-1.5">{{ $r['name'] }}</td>@foreach (['revenue', 'net', 'cash', 'inventory', 'inv_out', 'inv_in', 'cash_out', 'cash_in'] as $c)<td class="text-end tabular-nums">{{ $n($r[$c]) }}</td>@endforeach</tr>@endforeach</tbody>
    </table></div></x-card>
    @if ($canTransfer)
    <x-card :title="__('erp_rep.bt.title')">
        <form method="POST" action="{{ route('tenant.erp.branches.transfer') }}" class="grid gap-3 sm:grid-cols-3">@csrf<input type="hidden" name="ref" value="{{ \Illuminate\Support\Str::uuid() }}">
            <label class="text-sm">{{ __('erp_rep.bt.from') }}<select name="from_branch" required class="{{ $in }}">@foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
            <label class="text-sm">{{ __('erp_rep.bt.to') }}<select name="to_branch" required class="{{ $in }}">@foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
            <label class="text-sm">{{ __('erp.f.amount') }}<input name="amount" type="number" step="0.01" min="0.01" required class="{{ $in }}"></label>
            <label class="text-sm">{{ __('erp.f.date') }}<input name="date" type="date" value="{{ now()->toDateString() }}" class="{{ $in }}"></label>
            <label class="text-sm">{{ __('erp_rep.bt.account') }}<select name="role" class="{{ $in }}"><option value="cash">{{ __('erp_rep.bt.cash') }}</option><option value="bank">Bank</option><option value="pos_cash">POS</option></select></label>
            <label class="text-sm">{{ __('erp.f.memo') }}<input name="note" maxlength="200" class="{{ $in }}"></label>
            <p class="text-xs text-slate-500 sm:col-span-3">{{ __('erp_rep.bt.hint') }}</p>
            <div class="sm:col-span-3"><x-button type="submit">{{ __('erp_rep.bt.submit') }}</x-button></div>
        </form>
    </x-card>
    @endif
</x-layouts.admin>
