<x-layouts.admin :title="__('growth.payouts')">
    <x-card>
        <x-datatable :table="$table" :url="route('admin.affiliate-payouts.datatable')" id="payouts-table" />
    </x-card>
</x-layouts.admin>
