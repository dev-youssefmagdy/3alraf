<x-layouts.admin :title="__('growth.payouts')">
    <x-slot:header><x-ui.page-header :title="__('growth.payouts')" /></x-slot:header>
    <x-ui.data-table :table="$table" :url="route('admin.affiliate-payouts.datatable')" id="payouts-table" />
</x-layouts.admin>
