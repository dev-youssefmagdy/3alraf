<x-layouts.admin :title="__('orders.orders')">
    <x-slot:header><x-ui.page-header :title="__('orders.orders')" /></x-slot:header>
        <div class="space-y-4">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <x-ui.select name="status" :label="__('admin.status')" :options="$statuses" :placeholder="__('orders.all')" data-dt-filter="#orders-table" />
            <x-ui.select name="payment_status" :label="__('orders.fields.payment_status')" :options="$paymentStatuses" :placeholder="__('orders.all')" data-dt-filter="#orders-table" />
            <x-ui.select name="payment_method_id" :label="__('orders.fields.payment')" :options="$methods" :placeholder="__('orders.all')" data-dt-filter="#orders-table" />
            <x-ui.input name="from" type="date" :label="__('orders.fields.from')" data-dt-filter="#orders-table" />
            <x-ui.input name="to" type="date" :label="__('orders.fields.to')" data-dt-filter="#orders-table" />
        </div>
        <x-ui.data-table :table="$table" :url="route('admin.orders.datatable')" id="orders-table" />
        </div>
</x-layouts.admin>
