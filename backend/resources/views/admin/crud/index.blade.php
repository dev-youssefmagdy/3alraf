<x-layouts.admin :title="$title">
    <x-card>
        <x-slot:actions>
            @if (Route::has($route.'.create')) @can($perm.'.create')<x-button :href="route($route.'.create')">{{ __('admin.add_new') }}</x-button>@endcan @endif
        </x-slot:actions>
        <x-datatable :table="$table" :url="route($route.'.datatable')" :id="Str::slug($route, '-').'-table'" />
    </x-card>
</x-layouts.admin>
