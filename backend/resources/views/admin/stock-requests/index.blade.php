<x-layouts.admin :title="__('stock_requests.title')">
    <x-card>
        <div class="mb-4 max-w-xs">
            <x-select name="status" :label="__('admin.status')" :options="\App\Enums\StockRequestStatus::options()" :placeholder="__('orders.all')" data-dt-filter="#stock-requests-table" />
        </div>
        <x-datatable :table="$table" :url="route('admin.stock-requests.datatable')" id="stock-requests-table" />
    </x-card>
</x-layouts.admin>
