<x-layouts.admin :title="__('orders.returns')">
    <x-slot:header><x-ui.page-header :title="__('orders.returns')" /></x-slot:header>
        <div class="space-y-4">
        <div class="mb-4 max-w-xs">
            <x-ui.select name="status" :label="__('admin.status')" :options="$statuses" :placeholder="__('orders.all')" data-dt-filter="#returns-table" />
        </div>
        <x-ui.data-table :table="$table" :url="route('admin.returns.datatable')" id="returns-table" />
        </div>
</x-layouts.admin>
