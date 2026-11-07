<x-layouts.admin guard="central" :title="__('admin.attributes')">
    <x-card>
        <x-slot:actions>
            @can('attributes.create')<x-button :href="route('central.attributes.create')">{{ __('admin.add_new') }}</x-button>@endcan
        </x-slot:actions>
        <x-datatable :table="$table" :url="route('central.attributes.datatable')" id="attributes-table" />
    </x-card>
</x-layouts.admin>
