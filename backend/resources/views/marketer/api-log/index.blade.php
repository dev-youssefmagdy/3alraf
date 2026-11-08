<x-layouts.marketer :title="__('marketing.api_log.title')">
    <x-card :title="__('marketing.api_log.title')">
        <p class="mb-3 text-sm text-slate-600">{{ __('marketing.api_log.hint', ['days' => $days]) }}</p>
        <x-datatable :table="$table" :url="route('marketer.api-log.datatable')" />
    </x-card>
</x-layouts.marketer>
