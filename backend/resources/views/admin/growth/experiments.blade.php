<x-layouts.admin :title="__('growth_hub.experiments.title')">
    <div class="space-y-4">
        @can('growth.manage')
            <x-card :title="__('growth_hub.experiments.new')">
                @include('admin.growth._experiment-form', ['action' => route('admin.growth.experiments.store'), 'method' => 'POST', 'exp' => null])
            </x-card>
        @endcan
        <x-card :title="__('growth_hub.experiments.title')">
            <x-datatable :table="$table" :url="route('admin.growth.experiments.datatable')" />
        </x-card>
    </div>
</x-layouts.admin>
