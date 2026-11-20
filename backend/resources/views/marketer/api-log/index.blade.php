<x-layouts.marketer :title="__('marketing.api_log.title')">
    <x-slot:header><x-ui.page-header :title="__('marketing.api_log.title')" :subtitle="__('marketing.api_log.hint', ['days' => $days])" /></x-slot:header>
    <x-ui.data-table :table="$table" :url="route('marketer.api-log.datatable')" />
</x-layouts.marketer>
