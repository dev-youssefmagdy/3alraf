<x-layouts.admin :guard="$guard" :title="$data['title']">
    <div class="mb-3 flex flex-wrap items-center gap-3 text-sm text-slate-500">
        <a href="{{ route($guard.'.reports.index', $f->query()) }}" class="text-brand-600 hover:underline">&larr; {{ __('reports.reports') }}</a>
        <span>{{ __('reports.amounts_in', ['c' => $data['currency']]) }}@if ($guard === 'central') &middot; {{ $tenant ? $tenant->name : __('admin.all_tenants') }}@endif</span>
    </div>
    @if (session('status'))<div class="mb-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('status') }}</div>@endif
    @include('reports._filter', ['f' => $f, 'branches' => $branches, 'snapshot' => $data['snapshot']])
    @include('reports._cards', ['cards' => $data['cards']])
    <div class="grid gap-4 xl:grid-cols-3">
        <x-card :title="__('reports.chart')" class="xl:col-span-1">@include('reports._chart', ['chart' => $data['chart']])</x-card>
        <x-card :title="$data['title']" class="xl:col-span-2">
            <x-slot:actions>
                <a href="{{ route($guard.'.reports.export', ['report' => $key] + $f->query()) }}" class="btn-sm btn-secondary">{{ __('reports.export_csv') }}</a>
            </x-slot:actions>
            @include('reports._table', ['columns' => $data['columns'], 'rows' => $data['rows']])
            @if ($guard === 'tenant' && $key === 'cod-settlement' && auth('tenant')->user()?->can('orders.payments'))
                <form method="POST" action="{{ route('tenant.reports.cod-settle') }}" class="mt-4 flex flex-wrap items-center gap-2 border-t pt-4" onsubmit="return confirm(@js(__('reports.settle_confirm')))">
                    @csrf
                    <input type="hidden" name="from" value="{{ $f->from }}"><input type="hidden" name="to" value="{{ $f->to }}">@if ($f->branchId)<input type="hidden" name="branch_id" value="{{ $f->branchId }}">@endif
                    <select name="shipping_company_id" class="form-control w-auto" required>
                        @foreach (collect($data['rows'])->filter(fn ($r) => ! empty($r['company_id']) && (float) $r['owed'] > 0) as $r)<option value="{{ $r['company_id'] }}">{{ $r['company'] }} - {{ $r['owed'] }}</option>@endforeach
                    </select>
                    <button class="btn-sm btn-secondary">{{ __('reports.mark_settled') }}</button>
                </form>
            @endif
        </x-card>
    </div>
</x-layouts.admin>
