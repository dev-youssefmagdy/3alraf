<x-layouts.admin guard="tenant" :title="__('growth.affiliates')">
    <x-card>
        <x-datatable :table="$table" :url="route('tenant.affiliates.datatable')" id="affiliates-table" />
    </x-card>
</x-layouts.admin>
