<x-layouts.admin :title="__('admin.tags')">
    <x-card>
        <x-slot:actions>
            @can('tags.create')<x-button :href="route('admin.tags.create')">{{ __('admin.add_new') }}</x-button>@endcan
        </x-slot:actions>
        <x-datatable :table="$table" :url="route('admin.tags.datatable')" id="tags-table" />
    </x-card>
</x-layouts.admin>
