<x-layouts.admin guard="central" :title="__('admin.tags')">
    <x-card>
        <x-slot:actions>
            @can('tags.create')<x-button :href="route('central.tags.create')">{{ __('admin.add_new') }}</x-button>@endcan
        </x-slot:actions>
        <x-datatable :table="$table" :url="route('central.tags.datatable')" id="tags-table" />
    </x-card>
</x-layouts.admin>
