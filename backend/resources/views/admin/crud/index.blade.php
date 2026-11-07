<x-layouts.admin guard="tenant" :title="$title">
    <x-card>
        <x-slot:actions>
            @can($perm.'.create')<x-button :href="route($route.'.create')">{{ __('admin.add_new') }}</x-button>@endcan
        </x-slot:actions>
        <x-datatable :table="$table" :url="route($route.'.datatable')" :id="Str::slug($route, '-').'-table'" />
    </x-card>
</x-layouts.admin>
