<x-layouts.admin :title="__('growth.affiliates')">
    <x-card>
        <x-datatable :table="$table" :url="route('admin.affiliates.datatable')" id="affiliates-table" />
    </x-card>
</x-layouts.admin>
