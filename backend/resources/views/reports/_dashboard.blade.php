{{-- shared dashboard body (Figma Dashboard › Admin): date filter, 4 KPI cards, sales trend + orders by status, top products + payment methods. $d from ReportService::dashboard, $f filter --}}
@php $range = $f->from && $f->to ? "\u{2066}".$f->from.' → '.$f->to."\u{2069}" : null; @endphp
@include('reports._filter', ['f' => $f, 'branches' => []])
<p class="mb-3 text-panel-small text-panel-text-subtle">{{ __('reports.amounts_in', ['c' => $d['currency']]) }}</p>
@include('reports._cards', ['cards' => $d['cards']])
<div class="grid gap-4 xl:grid-cols-3">
    <x-ui.chart-card :title="__('reports.sales_trend')" :subtitle="$range" class="xl:col-span-2">@include('reports._chart', ['chart' => $d['salesChart']])</x-ui.chart-card>
    <x-ui.chart-card :title="__('reports.orders_by_status')">@include('reports._chart', ['chart' => $d['statusChart']])</x-ui.chart-card>
    <x-ui.card :title="__('reports.top_products')" :flush="true" class="xl:col-span-2">
        <div class="overflow-x-auto p-4 pt-2">@include('reports._table', ['columns' => $d['topProducts']['columns'], 'rows' => $d['topProducts']['rows']])</div>
    </x-ui.card>
    <x-ui.chart-card :title="__('reports.titles.payment-methods')">@include('reports._chart', ['chart' => $d['paymentChart']])</x-ui.chart-card>
</div>

@php
    // lists carry customer names / stock: shown only to staff who may open those modules (reports.view alone is not enough)
    $showRecent = isset($d['recentOrders']) && auth('admin')->user()?->can('orders.view');
    $showLow = isset($d['lowStockItems']) && auth('admin')->user()?->canAny(['products.view', 'stock.view']);
@endphp
@if ($showRecent || $showLow)
    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        @if ($showRecent)
            <x-ui.card :title="__('reports.recent_orders')" :flush="true" class="xl:col-span-2" data-dash-recent-orders>
                <x-slot:actions>@can('orders.view')<x-ui.link :href="route('admin.orders.index')" style="standalone">{{ __('reports.view_all') }}</x-ui.link>@endcan</x-slot:actions>
                @forelse ($d['recentOrders'] as $o)
                    @php
                        $tone = $o['status_class'] === 'badge-success' ? 'success' : ($o['status_class'] === 'badge-danger' ? 'danger' : 'neutral');
                        $row = ['title' => '#'.$o['number'], 'subtitle' => $o['customer'].' · '.$o['created_at']->diffForHumans()];
                    @endphp
                    @can('orders.view')
                        <x-ui.list-item :title="$row['title']" :subtitle="$row['subtitle']" :href="$o['url']">
                            <x-slot:trail><span class="text-sm font-semibold text-panel-text">{{ $o['total'] }}</span><x-ui.badge :tone="$tone" size="sm">{{ $o['status_label'] }}</x-ui.badge></x-slot:trail>
                        </x-ui.list-item>
                    @else
                        <x-ui.list-item :title="$row['title']" :subtitle="$row['subtitle']">
                            <x-slot:trail><span class="text-sm font-semibold text-panel-text">{{ $o['total'] }}</span><x-ui.badge :tone="$tone" size="sm">{{ $o['status_label'] }}</x-ui.badge></x-slot:trail>
                        </x-ui.list-item>
                    @endcan
                @empty
                    <p class="p-5 text-sm text-panel-text-subtle">{{ __('reports.no_recent_orders') }}</p>
                @endforelse
            </x-ui.card>
        @endif
        @if ($showLow)
            <x-ui.card :title="__('reports.low_stock_items')" :flush="true" data-dash-low-stock>
                <x-slot:actions>@can('stock.view')<x-ui.link :href="route('admin.stock.low')" style="standalone">{{ __('reports.view_all') }}</x-ui.link>@endcan</x-slot:actions>
                @forelse ($d['lowStockItems'] as $i)
                    @can('products.view')
                        <x-ui.list-item :title="$i['name']" :subtitle="$i['sku']" :href="$i['url']">
                            <x-slot:trail><x-ui.badge tone="warning" size="sm">{{ __('reports.stock_left', ['n' => $i['stock']]) }}</x-ui.badge></x-slot:trail>
                        </x-ui.list-item>
                    @else
                        <x-ui.list-item :title="$i['name']" :subtitle="$i['sku']">
                            <x-slot:trail><x-ui.badge tone="warning" size="sm">{{ __('reports.stock_left', ['n' => $i['stock']]) }}</x-ui.badge></x-slot:trail>
                        </x-ui.list-item>
                    @endcan
                @empty
                    <p class="p-5 text-sm text-panel-text-subtle">{{ __('reports.no_low_stock') }}</p>
                @endforelse
            </x-ui.card>
        @endif
    </div>
@endif
