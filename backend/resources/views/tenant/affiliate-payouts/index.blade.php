<x-layouts.admin guard="tenant" :title="__('growth.payouts')">
    <x-card>
        <x-datatable :table="$table" :url="route('tenant.affiliate-payouts.datatable')" id="payouts-table" />
    </x-card>
</x-layouts.admin>
