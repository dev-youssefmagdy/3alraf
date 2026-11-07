<x-layouts.admin guard="tenant" :title="__('orders.orders')">
    <x-card>
        <div class="mb-4 grid gap-3 sm:grid-cols-5">
            <x-select name="status" :label="__('admin.status')" :options="$statuses" :placeholder="__('orders.all')" data-dt-filter="#orders-table" />
            <x-select name="payment_status" :label="__('orders.fields.payment_status')" :options="$paymentStatuses" :placeholder="__('orders.all')" data-dt-filter="#orders-table" />
            <x-select name="payment_method_id" :label="__('orders.fields.payment')" :options="$methods" :placeholder="__('orders.all')" data-dt-filter="#orders-table" />
            <x-input name="from" type="date" :label="__('orders.fields.from')" data-dt-filter="#orders-table" />
            <x-input name="to" type="date" :label="__('orders.fields.to')" data-dt-filter="#orders-table" />
        </div>
        <x-datatable :table="$table" :url="route('tenant.orders.datatable')" id="orders-table" />
    </x-card>
</x-layouts.admin>
