<x-layouts.admin guard="tenant" :title="__('orders.returns')">
    <x-card>
        <div class="mb-4 max-w-xs">
            <x-select name="status" :label="__('admin.status')" :options="$statuses" :placeholder="__('orders.all')" data-dt-filter="#returns-table" />
        </div>
        <x-datatable :table="$table" :url="route('tenant.returns.datatable')" id="returns-table" />
    </x-card>
</x-layouts.admin>
