<x-layouts.admin :title="__('erp.r.'.$report)">
    <x-slot:header><x-ui.page-header :title="__('erp.r.'.$report)" /></x-slot:header>
    @php $n = fn ($v) => number_format((float) $v, 2); @endphp
    <x-ui.card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            @if ($report === 'general-ledger')<label>{{ __('erp.f.account') }}<select name="account_id" class="form-control mt-1 !w-auto"><option value="">-</option>@foreach ($accounts as $a)<option value="{{ $a->id }}" @selected($accountId == $a->id)>{{ $a->code }} {{ $a->name }}</option>@endforeach</select></label>@endif
            @unless (in_array($report, ['trial-balance', 'balance-sheet']))<label>{{ __('erp.f.from') }}<input type="date" name="from" value="{{ $from }}" class="form-control mt-1 !w-auto"></label>@endunless
            <label>{{ in_array($report, ['trial-balance', 'balance-sheet']) ? __('erp.r.as_of') : __('erp.f.to') }}<input type="date" name="to" value="{{ $to }}" class="form-control mt-1 !w-auto"></label>
            @if ($canBranch ?? true)<label>{{ __('erp.f.branch') }}<select name="branch_id" class="form-control mt-1 !w-auto"><option value="">{{ __('erp.f.all') }}</option>@foreach ($branches as $b)<option value="{{ $b->id }}" @selected($branchId == $b->id)>{{ $b->name }}</option>@endforeach</select></label>@endif
            <x-ui.button type="submit">{{ __('erp.f.filter') }}</x-ui.button>
        </form>
    </x-ui.card>
    @include('admin.erp._export')
    <x-ui.card>
    @if ($report === 'trial-balance')
        <x-ui.table><thead><tr><th>{{ __('erp.f.code') }}</th><th>{{ __('erp.f.account') }}</th><th class="text-end">{{ __('erp.f.debit') }}</th><th class="text-end">{{ __('erp.f.credit') }}</th></tr></thead><tbody>
        @foreach ($data['rows'] as $r)<tr><td data-label="" class="font-mono">{{ $r['account']->code }}</td><td data-label="{{ __('erp.f.account') }}">{{ $r['account']->name }}</td><td data-label="{{ __('erp.f.debit') }}" class="text-end tabular-nums">{{ (float) $r['debit'] ? $n($r['debit']) : '' }}</td><td data-label="{{ __('erp.f.credit') }}" class="text-end tabular-nums">{{ (float) $r['credit'] ? $n($r['credit']) : '' }}</td></tr>@endforeach
        </tbody><tfoot><tr class="font-semibold"><td colspan="2" class="py-2">{{ __('erp.f.total') }}</td><td class="text-end tabular-nums">{{ $n($data['total_debit']) }}</td><td class="text-end tabular-nums">{{ $n($data['total_credit']) }}</td></tr></tfoot></x-ui.table>
        @unless ($data['balanced'])<p class="mt-3 text-panel-danger-fg">{{ __('erp.r.unbalanced') }}</p>@endunless
    @elseif ($report === 'general-ledger')
        @if (! $data)<p class="text-panel-text-subtle">{{ __('erp.r.pick_account') }}</p>
        @else
        <x-ui.table><thead><tr><th>{{ __('erp.f.date') }}</th><th>{{ __('erp.f.number') }}</th><th>{{ __('erp.f.memo') }}</th><th class="text-end">{{ __('erp.f.debit') }}</th><th class="text-end">{{ __('erp.f.credit') }}</th><th class="text-end">{{ __('erp.f.balance') }}</th></tr></thead><tbody>
        @if ($data['truncated'])<tr><td colspan="6" class="text-panel-warning-fg">{{ __('erp_rep.truncated', ['n' => $data['limit']]) }}</td></tr>@endif
        <tr class="bg-panel-surface-sunken"><td colspan="5">{{ __('erp.r.opening') }}</td><td data-label="{{ __('erp.f.number') }}" class="text-end tabular-nums">{{ $n($data['opening']) }}</td></tr>
        @foreach ($data['rows'] as $r)<tr><td data-label="">{{ $r['line']->entry->entry_date->format('Y-m-d') }}</td><td data-label="{{ __('erp.f.number') }}">@include('admin.erp._jlink', ['id' => $r['line']->entry_id, 'no' => $r['line']->entry->number])</td><td data-label="{{ __('erp.f.memo') }}">{{ $r['line']->memo ?: $r['line']->entry->description }}</td><td data-label="{{ __('erp.f.debit') }}" class="text-end tabular-nums">{{ (float) $r['line']->debit ? $n($r['line']->debit) : '' }}</td><td data-label="{{ __('erp.f.credit') }}" class="text-end tabular-nums">{{ (float) $r['line']->credit ? $n($r['line']->credit) : '' }}</td><td data-label="{{ __('erp.f.balance') }}" class="text-end tabular-nums">{{ $n($r['balance']) }}</td></tr>@endforeach
        <tr class="font-semibold"><td colspan="5">{{ __('erp.r.closing') }}</td><td data-label="{{ __('erp.f.number') }}" class="text-end tabular-nums">{{ $n($data['closing']) }}</td></tr></tbody></x-ui.table>
        @endif
    @elseif ($report === 'profit-loss')
        @foreach (['revenue' => 'revenue', 'cogs' => 'cogs', 'opex' => 'opex'] as $g => $k)
            <h3 class="mb-1 mt-4 font-semibold">{{ __('erp.r.'.$k) }}</h3>
            @foreach ($data['groups'][$g] as $r)<div class="flex justify-between border-b border-panel-border py-1 text-sm"><span>{{ $r['account']->code }} {{ $r['account']->name }}</span><span class="tabular-nums">{{ $n($r['amount']) }}</span></div>@endforeach
            <div class="flex justify-between py-1 text-sm font-semibold"><span>{{ __('erp.f.total') }}</span><span class="tabular-nums">{{ $n($data['totals'][$g]) }}</span></div>
            @if ($g === 'cogs')<div class="mt-2 flex justify-between rounded bg-panel-surface-sunken px-2 py-1.5 font-semibold"><span>{{ __('erp.r.gross') }}</span><span class="tabular-nums">{{ $n($data['gross_profit']) }}</span></div>@endif
        @endforeach
        <div class="mt-4 flex justify-between rounded bg-panel-accent-soft px-2 py-2 text-lg font-bold"><span>{{ __('erp.r.net') }}</span><span class="tabular-nums">{{ $n($data['net_profit']) }}</span></div>
    @else
        @foreach (['asset', 'liability', 'equity'] as $s)
            <h3 class="mb-1 mt-4 font-semibold">{{ __('erp.types.'.$s) }}</h3>
            @foreach ($data['sections'][$s] as $r)<div class="flex justify-between border-b border-panel-border py-1 text-sm"><span>{{ $r['account']->code }} {{ $r['account']->name }}</span><span class="tabular-nums">{{ $n($r['amount']) }}</span></div>@endforeach
            @if ($s === 'equity')<div class="flex justify-between border-b border-panel-border py-1 text-sm"><span>{{ __('erp.r.current_earnings') }}</span><span class="tabular-nums">{{ $n($data['current_earnings']) }}</span></div>@endif
            <div class="flex justify-between py-1 text-sm font-semibold"><span>{{ __('erp.f.total') }}</span><span class="tabular-nums">{{ $n($s === 'equity' ? $data['total_equity'] : $data['totals'][$s]) }}</span></div>
        @endforeach
        @unless ($data['balanced'])<p class="mt-3 text-panel-danger-fg">{{ __('erp.r.unbalanced') }}</p>@endunless
    @endif
    </x-ui.card>
</x-layouts.admin>
