<x-layouts.admin :title="__('admin.languages')">
    <x-slot:header><x-ui.page-header :title="__('admin.languages')">@can('languages.create')<x-ui.button :href="route('admin.languages.create')">{{ __('admin.add_new') }}</x-ui.button>@endcan</x-ui.page-header></x-slot:header>
        <div class="space-y-4">
        <x-ui.data-table :table="$table" :url="route('admin.languages.datatable')" id="languages-table" />
        </div>
</x-layouts.admin>
