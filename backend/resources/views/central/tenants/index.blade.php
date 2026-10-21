<x-layouts.admin guard="central" :title="__('admin.tenants')">
    <x-card>
        <x-slot:actions>
            @can('tenants.create')<x-button :href="route('central.tenants.create')">{{ __('admin.add_new') }}</x-button>@endcan
        </x-slot:actions>
        <x-datatable :table="$table" :url="route('central.tenants.datatable')" id="tenants-table" />
    </x-card>
</x-layouts.admin>
