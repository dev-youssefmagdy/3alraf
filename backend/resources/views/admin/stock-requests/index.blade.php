<x-layouts.admin :title="__('stock_requests.title')">
    <x-slot:header><x-ui.page-header :title="__('stock_requests.title')" /></x-slot:header>
        <div class="space-y-4">
        <div class="mb-4 max-w-xs">
            <x-ui.select name="status" :label="__('admin.status')" :options="\App\Enums\StockRequestStatus::options()" :placeholder="__('orders.all')" data-dt-filter="#stock-requests-table" />
        </div>
        <x-ui.data-table :table="$table" :url="route('admin.stock-requests.datatable')" id="stock-requests-table" />
        </div>
</x-layouts.admin>
