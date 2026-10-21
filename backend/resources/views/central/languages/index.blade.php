<x-layouts.admin guard="central" :title="__('admin.languages')">
    <x-card>
        <x-slot:actions>
            @can('languages.create')<x-button :href="route('central.languages.create')">{{ __('admin.add_new') }}</x-button>@endcan
        </x-slot:actions>
        <x-datatable :table="$table" :url="route('central.languages.datatable')" id="languages-table" />
    </x-card>
</x-layouts.admin>
