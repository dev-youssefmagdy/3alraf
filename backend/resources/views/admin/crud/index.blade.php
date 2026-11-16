{{-- Generic resource list (ResourceController): page header (title + add) → table card (Figma Panel/Page header + Panel/Card). --}}
<x-layouts.panel :title="$title">
    <x-slot:header>
        <x-ui.page-header :title="$title">
            @if (Route::has($route.'.create')) @can($perm.'.create')<x-button :href="route($route.'.create')">+ {{ __('admin.add_new') }}</x-button>@endcan @endif
        </x-ui.page-header>
    </x-slot:header>
    <x-card>
        <x-datatable :table="$table" :url="route($route.'.datatable')" :id="Str::slug($route, '-').'-table'" />
    </x-card>
</x-layouts.panel>
