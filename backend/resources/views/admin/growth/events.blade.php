<x-layouts.admin guard="tenant" :title="__('growth_hub.events.title')">
    <x-card :title="__('growth_hub.events.title')">
        <p class="mb-3 text-sm text-slate-600">{{ __('growth_hub.events.hint') }}</p>
        <x-datatable :table="$table" :url="route('tenant.growth.events.datatable')" />
    </x-card>
    @include('tenant.growth._actions')
</x-layouts.admin>
