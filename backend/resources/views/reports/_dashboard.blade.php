{{-- shared dashboard body. $d from ReportService::dashboard, $f filter, $guard --}}
@include('reports._filter', ['f' => $f, 'branches' => []])
<p class="mb-3 text-sm text-slate-500">{{ __('reports.amounts_in', ['c' => $d['currency']]) }}</p>
@include('reports._cards', ['cards' => $d['cards']])
<div class="grid gap-4 lg:grid-cols-3">
    <x-card :title="__('reports.sales_trend')" class="lg:col-span-2">@include('reports._chart', ['chart' => $d['salesChart']])</x-card>
    <x-card :title="__('reports.orders_by_status')">@include('reports._chart', ['chart' => $d['statusChart']])</x-card>
    <x-card :title="__('reports.titles.payment-methods')">@include('reports._chart', ['chart' => $d['paymentChart']])</x-card>
    <x-card :title="__('reports.top_products')" class="lg:col-span-2">
        @include('reports._table', ['columns' => $d['topProducts']['columns'], 'rows' => $d['topProducts']['rows']])
    </x-card>
    @isset($d['tenants'])
        <x-card :title="__('reports.sales_by_tenant')">@include('reports._chart', ['chart' => $d['tenantChart']])</x-card>
        <x-card :title="__('reports.tenants_breakdown')" class="lg:col-span-2">
            @include('reports._table', ['columns' => $d['tenants']['columns'], 'rows' => $d['tenants']['rows']])
        </x-card>
    @endisset
</div>
