<x-layouts.admin :title="__('erp.cards.dashboard')[0]">
    <x-slot:header><x-ui.page-header :title="__('erp.cards.dashboard')[0]" /></x-slot:header>
    @php $n = fn ($v) => number_format((float) $v, 2); $in = 'form-control mt-1 !w-auto'; $k = $d['kpis']; @endphp
    <x-ui.card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <label>{{ __('erp.f.from') }}<input type="date" name="from" value="{{ $f->from }}" class="{{ $in }}"></label>
            <label>{{ __('erp.f.to') }}<input type="date" name="to" value="{{ $f->to }}" class="{{ $in }}"></label>
            @if ($canBranch)<label>{{ __('erp.f.branch') }}<select name="branch_id" class="{{ $in }}"><option value="">{{ __('erp.f.all') }}</option>@foreach ($branches as $b)<option value="{{ $b->id }}" @selected($f->branchId == $b->id)>{{ $b->name }}</option>@endforeach</select></label>@endif
            <x-ui.button type="submit">{{ __('erp.f.filter') }}</x-ui.button>
        </form>
    </x-ui.card>
    <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-kpis>
        @foreach (['total_revenue', 'net_revenue', 'cogs', 'gross_profit', 'opex', 'net_profit', 'cash', 'receivable', 'payable', 'inventory', 'equity', 'liabilities'] as $key)
            <div class="rounded-xl border border-panel-border bg-panel-surface p-4"><div class="text-xs text-panel-text-subtle">{{ __('erp_rep.kpi.'.$key) }}</div><div class="mt-1 text-xl font-bold tabular-nums {{ $key === 'net_profit' && (float) $k[$key] < 0 ? 'text-panel-danger-fg' : '' }}" data-kpi="{{ $key }}">{{ $n($k[$key]) }}</div></div>
        @endforeach
    </div>
    <div class="mb-5 grid gap-5 lg:grid-cols-3">
        <x-ui.card :title="__('erp_rep.dash.trend')" class="lg:col-span-2">@include('reports._chart', ['chart' => $d['trend']])</x-ui.card>
        <x-ui.card :title="__('erp_rep.dash.top_expenses')">@include('reports._chart', ['chart' => $d['expenseChart']])</x-ui.card>
        <x-ui.card :title="__('erp_rep.dash.by_branch')" class="lg:col-span-3">@include('reports._chart', ['chart' => $d['branchChart']])</x-ui.card>
    </div>
    <x-ui.card :title="__('erp_rep.dash.recent')">
        <x-ui.table :mobile="false"><tbody>
        @forelse ($d['recent'] as $e)
            <tr><td>{{ $e->entry_date->format('Y-m-d') }}</td><td>@include('admin.erp._jlink', ['id' => $e->id, 'no' => $e->number])</td><td>{{ $e->description }}</td><td class="text-end tabular-nums">{{ $n($e->total) }}</td></tr>
        @empty<tr><td>{{ __('erp_rep.empty') }}</td></tr>@endforelse
        </tbody></x-ui.table>
    </x-ui.card>
</x-layouts.admin>
