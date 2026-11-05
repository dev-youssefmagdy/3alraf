<x-layouts.admin guard="tenant" :title="__('erp.r.'.$report)">
    @php $n = fn ($v) => number_format((float) $v, 2); @endphp
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            @if ($report === 'general-ledger')<label>{{ __('erp.f.account') }}<select name="account_id" class="mt-1 block rounded border border-slate-300 px-2 py-1.5"><option value="">-</option>@foreach ($accounts as $a)<option value="{{ $a->id }}" @selected($accountId == $a->id)>{{ $a->code }} {{ $a->name }}</option>@endforeach</select></label>@endif
            @unless (in_array($report, ['trial-balance', 'balance-sheet']))<label>{{ __('erp.f.from') }}<input type="date" name="from" value="{{ $from }}" class="mt-1 block rounded border border-slate-300 px-2 py-1.5"></label>@endunless
            <label>{{ in_array($report, ['trial-balance', 'balance-sheet']) ? __('erp.r.as_of') : __('erp.f.to') }}<input type="date" name="to" value="{{ $to }}" class="mt-1 block rounded border border-slate-300 px-2 py-1.5"></label>
            @if ($canBranch ?? true)<label>{{ __('erp.f.branch') }}<select name="branch_id" class="mt-1 block rounded border border-slate-300 px-2 py-1.5"><option value="">{{ __('erp.f.all') }}</option>@foreach ($branches as $b)<option value="{{ $b->id }}" @selected($branchId == $b->id)>{{ $b->name }}</option>@endforeach</select></label>@endif
            <x-button type="submit">{{ __('erp.f.filter') }}</x-button>
        </form>
    </x-card>
    @include('tenant.erp._export')
    <x-card>
    @if ($report === 'trial-balance')
        <table class="w-full text-sm"><thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp.f.code') }}</th><th class="text-start">{{ __('erp.f.account') }}</th><th class="text-end">{{ __('erp.f.debit') }}</th><th class="text-end">{{ __('erp.f.credit') }}</th></tr></thead><tbody>
        @foreach ($data['rows'] as $r)<tr class="border-b border-slate-100"><td class="py-1.5 font-mono">{{ $r['account']->code }}</td><td>{{ $r['account']->name }}</td><td class="text-end tabular-nums">{{ (float) $r['debit'] ? $n($r['debit']) : '' }}</td><td class="text-end tabular-nums">{{ (float) $r['credit'] ? $n($r['credit']) : '' }}</td></tr>@endforeach
        </tbody><tfoot><tr class="font-semibold"><td colspan="2" class="py-2">{{ __('erp.f.total') }}</td><td class="text-end tabular-nums">{{ $n($data['total_debit']) }}</td><td class="text-end tabular-nums">{{ $n($data['total_credit']) }}</td></tr></tfoot></table>
        @unless ($data['balanced'])<p class="mt-3 text-red-600">{{ __('erp.r.unbalanced') }}</p>@endunless
    @elseif ($report === 'general-ledger')
        @if (! $data)<p class="text-slate-500">{{ __('erp.r.pick_account') }}</p>
        @else
        <table class="w-full text-sm"><thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp.f.date') }}</th><th class="text-start">{{ __('erp.f.number') }}</th><th class="text-start">{{ __('erp.f.memo') }}</th><th class="text-end">{{ __('erp.f.debit') }}</th><th class="text-end">{{ __('erp.f.credit') }}</th><th class="text-end">{{ __('erp.f.balance') }}</th></tr></thead><tbody>
        @if ($data['truncated'])<tr><td colspan="6" class="py-1.5 text-amber-700">{{ __('erp_rep.truncated', ['n' => $data['limit']]) }}</td></tr>@endif
        <tr class="bg-slate-50"><td colspan="5" class="py-1.5">{{ __('erp.r.opening') }}</td><td class="text-end tabular-nums">{{ $n($data['opening']) }}</td></tr>
        @foreach ($data['rows'] as $r)<tr class="border-b border-slate-100"><td class="py-1.5">{{ $r['line']->entry->entry_date->format('Y-m-d') }}</td><td>@include('tenant.erp._jlink', ['id' => $r['line']->entry_id, 'no' => $r['line']->entry->number])</td><td>{{ $r['line']->memo ?: $r['line']->entry->description }}</td><td class="text-end tabular-nums">{{ (float) $r['line']->debit ? $n($r['line']->debit) : '' }}</td><td class="text-end tabular-nums">{{ (float) $r['line']->credit ? $n($r['line']->credit) : '' }}</td><td class="text-end tabular-nums">{{ $n($r['balance']) }}</td></tr>@endforeach
        <tr class="font-semibold"><td colspan="5" class="py-2">{{ __('erp.r.closing') }}</td><td class="text-end tabular-nums">{{ $n($data['closing']) }}</td></tr></tbody></table>
        @endif
    @elseif ($report === 'profit-loss')
        @foreach (['revenue' => 'revenue', 'cogs' => 'cogs', 'opex' => 'opex'] as $g => $k)
            <h3 class="mb-1 mt-4 font-semibold">{{ __('erp.r.'.$k) }}</h3>
            @foreach ($data['groups'][$g] as $r)<div class="flex justify-between border-b border-slate-100 py-1 text-sm"><span>{{ $r['account']->code }} {{ $r['account']->name }}</span><span class="tabular-nums">{{ $n($r['amount']) }}</span></div>@endforeach
            <div class="flex justify-between py-1 text-sm font-semibold"><span>{{ __('erp.f.total') }}</span><span class="tabular-nums">{{ $n($data['totals'][$g]) }}</span></div>
            @if ($g === 'cogs')<div class="mt-2 flex justify-between rounded bg-slate-50 px-2 py-1.5 font-semibold"><span>{{ __('erp.r.gross') }}</span><span class="tabular-nums">{{ $n($data['gross_profit']) }}</span></div>@endif
        @endforeach
        <div class="mt-4 flex justify-between rounded bg-brand-50 px-2 py-2 text-lg font-bold"><span>{{ __('erp.r.net') }}</span><span class="tabular-nums">{{ $n($data['net_profit']) }}</span></div>
    @else
        @foreach (['asset', 'liability', 'equity'] as $s)
            <h3 class="mb-1 mt-4 font-semibold">{{ __('erp.types.'.$s) }}</h3>
            @foreach ($data['sections'][$s] as $r)<div class="flex justify-between border-b border-slate-100 py-1 text-sm"><span>{{ $r['account']->code }} {{ $r['account']->name }}</span><span class="tabular-nums">{{ $n($r['amount']) }}</span></div>@endforeach
            @if ($s === 'equity')<div class="flex justify-between border-b border-slate-100 py-1 text-sm"><span>{{ __('erp.r.current_earnings') }}</span><span class="tabular-nums">{{ $n($data['current_earnings']) }}</span></div>@endif
            <div class="flex justify-between py-1 text-sm font-semibold"><span>{{ __('erp.f.total') }}</span><span class="tabular-nums">{{ $n($s === 'equity' ? $data['total_equity'] : $data['totals'][$s]) }}</span></div>
        @endforeach
        @unless ($data['balanced'])<p class="mt-3 text-red-600">{{ __('erp.r.unbalanced') }}</p>@endunless
    @endif
    </x-card>
</x-layouts.admin>
