<x-layouts.admin guard="central" :title="__('ai.menu.models')">
    <x-card>
        <x-slot:actions>
            @can('ai_models.create')<x-button :href="route('central.ai.models.create')">{{ __('admin.add_new') }}</x-button>@endcan
        </x-slot:actions>
        <x-datatable :table="$table" :url="route('central.ai.models.datatable')" id="ai-models-table" />
    </x-card>
</x-layouts.admin>
