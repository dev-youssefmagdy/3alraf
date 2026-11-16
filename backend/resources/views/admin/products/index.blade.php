<x-layouts.admin :title="__('admin.products')">
    <x-slot:header>
        <x-ui.page-header :title="__('admin.products')" :count="number_format(\App\Models\Product::query()->count())">
            @can('products.create')<x-button :href="route('admin.products.create')">+ {{ __('admin.add_new') }}</x-button>@endcan
        </x-ui.page-header>
    </x-slot:header>
    <x-card>
        <x-datatable :table="$table" :url="route('admin.products.datatable')" id="products-table" />
    </x-card>
</x-layouts.admin>
