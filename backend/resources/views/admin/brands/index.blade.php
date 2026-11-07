<x-layouts.admin :title="__('admin.brands')">
    <x-card>
        <x-slot:actions>
            @can('brands.create')<x-button :href="route('admin.brands.create')">{{ __('admin.add_new') }}</x-button>@endcan
        </x-slot:actions>
        <x-datatable :table="$table" :url="route('admin.brands.datatable')" id="brands-table" />
    </x-card>
</x-layouts.admin>
