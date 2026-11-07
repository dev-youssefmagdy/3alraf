<x-layouts.admin :title="__('admin.languages')">
    <x-card>
        <x-slot:actions>
            @can('languages.create')<x-button :href="route('admin.languages.create')">{{ __('admin.add_new') }}</x-button>@endcan
        </x-slot:actions>
        <x-datatable :table="$table" :url="route('admin.languages.datatable')" id="languages-table" />
    </x-card>
</x-layouts.admin>
