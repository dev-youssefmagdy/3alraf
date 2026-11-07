<x-layouts.admin guard="tenant" :title="__('admin.catalog')">
    <x-card>
        <x-slot:actions>
            @can('catalog.create')<x-button :href="route('tenant.catalog.available')">{{ __('admin.add_products') }}</x-button>@endcan
        </x-slot:actions>
        <x-datatable :table="$table" :url="route('tenant.catalog.datatable')" id="catalog-table" />
    </x-card>
</x-layouts.admin>
