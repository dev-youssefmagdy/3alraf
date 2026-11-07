<x-layouts.admin :title="__('growth_hub.approvals.title')">
    <x-card :title="__('growth_hub.approvals.title')">
        <p class="mb-3 text-sm text-slate-600">{{ __('growth_hub.approvals.hint') }}</p>
        <x-datatable :table="$table" :url="route('admin.growth.approvals.datatable')" />
    </x-card>
    @include('admin.growth._actions')
</x-layouts.admin>
