<x-layouts.marketer :title="__('marketing.nav.suggestions_log')">
    @php $t = fn ($k) => __('marketing.suggestions.'.$k); @endphp
    <x-slot:header><x-ui.page-header :title="__('marketing.nav.suggestions_log')" /></x-slot:header>
    <div class="space-y-4">
        <x-ui.heading :level="2">{{ $t('executions') }}</x-ui.heading>
        <x-ui.data-table :table="$executions" :url="route('marketer.suggestions.log.executions')" id="exec-table" />
        <x-ui.heading :level="2" class="pt-2">{{ $t('approvals') }}</x-ui.heading>
        <x-ui.data-table :table="$approvals" :url="route('marketer.suggestions.log.approvals')" id="approval-table" />
    </div>
</x-layouts.marketer>
