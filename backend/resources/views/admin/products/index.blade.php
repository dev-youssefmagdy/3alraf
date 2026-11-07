<x-layouts.admin :title="__('admin.products')">
    <x-card>
        <x-slot:actions>
            @can('products.create')<x-button variant="secondary" :href="route('admin.products.available')">{{ __('admin.activate_products') }}</x-button>@endcan
            @can('products.create')<x-button :href="route('admin.products.create')">{{ __('admin.add_new') }}</x-button>@endcan
        </x-slot:actions>
        <x-datatable :table="$table" :url="route('admin.products.datatable')" id="products-table" />
    </x-card>
</x-layouts.admin>
