<x-layouts.admin :title="__('admin.brands')">
    <x-slot:header><x-ui.page-header :title="__('admin.brands')">@can('brands.create')<x-ui.button :href="route('admin.brands.create')">{{ __('admin.add_new') }}</x-ui.button>@endcan</x-ui.page-header></x-slot:header>
        <div class="space-y-4">
        <x-ui.data-table :table="$table" :url="route('admin.brands.datatable')" id="brands-table" />
        </div>
</x-layouts.admin>
