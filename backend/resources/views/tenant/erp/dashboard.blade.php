<x-layouts.admin guard="tenant" :title="__('erp.cards.dashboard')[0]">
    @php $n = fn ($v) => number_format((float) $v, 2); $in = 'mt-1 block rounded border border-slate-300 px-2 py-1.5'; $k = $d['kpis']; @endphp
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <label>{{ __('erp.f.from') }}<input type="date" name="from" value="{{ $f->from }}" class="{{ $in }}"></label>
            <label>{{ __('erp.f.to') }}<input type="date" name="to" value="{{ $f->to }}" class="{{ $in }}"></label>
            @if ($canBranch)<label>{{ __('erp.f.branch') }}<select name="branch_id" class="{{ $in }}"><option value="">{{ __('erp.f.all') }}</option>@foreach ($branches as $b)<option value="{{ $b->id }}" @selected($f->branchId == $b->id)>{{ $b->name }}</option>@endforeach</select></label>@endif
            <x-button type="submit">{{ __('erp.f.filter') }}</x-button>
        </form>
    </x-card>
    <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-kpis>
        @foreach (['total_revenue', 'net_revenue', 'cogs', 'gross_profit', 'opex', 'net_profit', 'cash', 'receivable', 'payable', 'inventory', 'equity', 'liabilities'] as $key)
            <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">{{ __('erp_rep.kpi.'.$key) }}</div><div class="mt-1 text-xl font-bold tabular-nums {{ $key === 'net_profit' && (float) $k[$key] < 0 ? 'text-red-600' : '' }}" data-kpi="{{ $key }}">{{ $n($k[$key]) }}</div></div>
        @endforeach
    </div>
    <div class="mb-5 grid gap-5 lg:grid-cols-3">
        <x-card :title="__('erp_rep.dash.trend')" class="lg:col-span-2">@include('reports._chart', ['chart' => $d['trend']])</x-card>
        <x-card :title="__('erp_rep.dash.top_expenses')">@include('reports._chart', ['chart' => $d['expenseChart']])</x-card>
        <x-card :title="__('erp_rep.dash.by_branch')" class="lg:col-span-3">@include('reports._chart', ['chart' => $d['branchChart']])</x-card>
    </div>
    <x-card :title="__('erp_rep.dash.recent')">
        <table class="w-full text-sm"><tbody>
        @forelse ($d['recent'] as $e)
            <tr class="border-b border-slate-100"><td class="py-1.5">{{ $e->entry_date->format('Y-m-d') }}</td><td>@include('tenant.erp._jlink', ['id' => $e->id, 'no' => $e->number])</td><td>{{ $e->description }}</td><td class="text-end tabular-nums">{{ $n($e->total) }}</td></tr>
        @empty<tr><td class="py-3 text-slate-500">{{ __('erp_rep.empty') }}</td></tr>@endforelse
        </tbody></table>
    </x-card>
</x-layouts.admin>
