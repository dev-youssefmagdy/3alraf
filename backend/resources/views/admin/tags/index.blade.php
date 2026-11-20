<x-layouts.admin :title="__('admin.tags')">
    <x-slot:header><x-ui.page-header :title="__('admin.tags')">@can('tags.create')<x-ui.button :href="route('admin.tags.create')">{{ __('admin.add_new') }}</x-ui.button>@endcan</x-ui.page-header></x-slot:header>
        <div class="space-y-4">
        <x-ui.data-table :table="$table" :url="route('admin.tags.datatable')" id="tags-table" />
        </div>
</x-layouts.admin>
