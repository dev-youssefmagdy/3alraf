<x-layouts.admin :title="__('admin.catalog')">
    <x-card>
        <x-slot:actions>
            @can('products.view')<x-button variant="secondary" :href="route('admin.products.index')">{{ __('admin.products') }}</x-button>@endcan
            @can('catalog.create')<x-button :href="route('admin.catalog.available')">{{ __('admin.activate_products') }}</x-button>@endcan
        </x-slot:actions>
        <x-datatable :table="$table" :url="route('admin.catalog.datatable')" id="catalog-table" />
    </x-card>
</x-layouts.admin>
