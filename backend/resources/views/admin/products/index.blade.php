<x-layouts.admin guard="central" :title="__('admin.products')">
    <x-card>
        <x-slot:actions>
            @can('products.create')<x-button :href="route('central.products.create')">{{ __('admin.add_new') }}</x-button>@endcan
        </x-slot:actions>
        <x-datatable :table="$table" :url="route('central.products.datatable')" id="products-table" />
    </x-card>
</x-layouts.admin>
