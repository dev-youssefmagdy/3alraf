<x-layouts.admin :title="__('erp.cards.branches')[0]">
    <x-slot:header><x-ui.page-header :title="__('erp.cards.branches')[0]" /></x-slot:header>
    @include('admin.erp._flash')
    @php $n = fn ($v) => number_format((float) $v, 2); $in = 'form-control mt-1'; @endphp
    <x-ui.card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <label>{{ __('erp.f.from') }}<input type="date" name="from" value="{{ $f->from }}" class="{{ $in }}"></label>
            <label>{{ __('erp.f.to') }}<input type="date" name="to" value="{{ $f->to }}" class="{{ $in }}"></label>
            <x-ui.button type="submit">{{ __('erp.f.filter') }}</x-ui.button>
            @can('erp.reports.view')
                <a class="text-panel-accent-text" href="{{ route('admin.erp.reports.show', ['report' => 'branch-profitability'] + $f->query()) }}">{{ __('erp_rep.t.branch-profitability') }}</a>
            @endcan
        </form>
    </x-ui.card>
    <x-ui.card class="mb-5"><x-ui.table>
        <thead><tr><th>{{ __('erp.f.branch') }}</th><th class="text-end">{{ __('erp_rep.col.revenue') }}</th><th class="text-end">{{ __('erp_rep.col.net') }}</th><th class="text-end">{{ __('erp_rep.bt.cash') }}</th><th class="text-end">{{ __('erp_rep.bt.inventory') }}</th><th class="text-end">{{ __('erp_rep.bt.inv_out') }}</th><th class="text-end">{{ __('erp_rep.bt.inv_in') }}</th><th class="text-end">{{ __('erp_rep.bt.cash_out') }}</th><th class="text-end">{{ __('erp_rep.bt.cash_in') }}</th></tr></thead>
        <tbody>@foreach ($rows as $r)<tr><td data-label="">{{ $r['name'] }}</td>@foreach (['revenue', 'net', 'cash', 'inventory', 'inv_out', 'inv_in', 'cash_out', 'cash_in'] as $c)<td data-label="{{ __('erp_rep.col.revenue') }}" class="text-end tabular-nums">{{ $n($r[$c]) }}</td>@endforeach</tr>@endforeach</tbody>
    </x-ui.table></x-ui.card>
    @if ($canTransfer)
    <x-ui.card :title="__('erp_rep.bt.title')">
        <form method="POST" action="{{ route('admin.erp.branches.transfer') }}" class="grid gap-3 sm:grid-cols-3">@csrf<input type="hidden" name="ref" value="{{ \Illuminate\Support\Str::uuid() }}">
            <label class="text-sm">{{ __('erp_rep.bt.from') }}<select name="from_branch" required class="{{ $in }}">@foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
            <label class="text-sm">{{ __('erp_rep.bt.to') }}<select name="to_branch" required class="{{ $in }}">@foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
            <label class="text-sm">{{ __('erp.f.amount') }}<input name="amount" type="number" step="0.01" min="0.01" required class="{{ $in }}"></label>
            <label class="text-sm">{{ __('erp.f.date') }}<input name="date" type="date" value="{{ now()->toDateString() }}" class="{{ $in }}"></label>
            <label class="text-sm">{{ __('erp_rep.bt.account') }}<select name="role" class="{{ $in }}"><option value="cash">{{ __('erp_rep.bt.cash') }}</option><option value="bank">Bank</option><option value="pos_cash">POS</option></select></label>
            <label class="text-sm">{{ __('erp.f.memo') }}<input name="note" maxlength="200" class="{{ $in }}"></label>
            <p class="text-xs text-panel-text-subtle sm:col-span-3">{{ __('erp_rep.bt.hint') }}</p>
            <div class="sm:col-span-3"><x-ui.button type="submit">{{ __('erp_rep.bt.submit') }}</x-ui.button></div>
        </form>
    </x-ui.card>
    @endif
</x-layouts.admin>
