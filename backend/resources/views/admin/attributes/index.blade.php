<x-layouts.admin :title="__('admin.attributes')">
    <x-slot:header><x-ui.page-header :title="__('admin.attributes')">@can('attributes.create')<x-ui.button :href="route('admin.attributes.create')">{{ __('admin.add_new') }}</x-ui.button>@endcan</x-ui.page-header></x-slot:header>
        <div class="space-y-4">
        <x-ui.data-table :table="$table" :url="route('admin.attributes.datatable')" id="attributes-table" />
        </div>
</x-layouts.admin>
