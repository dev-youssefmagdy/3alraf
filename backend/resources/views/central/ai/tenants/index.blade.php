<x-layouts.admin guard="central" :title="__('ai.menu.tenants')">
    <x-card>
        <x-datatable :table="$table" :url="route('central.ai.tenants.datatable')" id="ai-tenants-table" />
    </x-card>
</x-layouts.admin>
