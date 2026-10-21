<x-layouts.admin guard="central" :title="__('admin.brands')">
    <x-card>
        <x-slot:actions>
            @can('brands.create')<x-button :href="route('central.brands.create')">{{ __('admin.add_new') }}</x-button>@endcan
        </x-slot:actions>
        <x-datatable :table="$table" :url="route('central.brands.datatable')" id="brands-table" />
    </x-card>
</x-layouts.admin>
