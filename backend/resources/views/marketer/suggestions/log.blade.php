<x-layouts.marketer :title="__('marketing.nav.suggestions_log')">
    @php $t = fn ($k) => __('marketing.suggestions.'.$k); @endphp
    <div class="space-y-4">
        <h1 class="text-xl font-bold text-slate-800">{{ __('marketing.nav.suggestions_log') }}</h1>
        <x-card :title="$t('executions')">
            <x-datatable :table="$executions" :url="route('marketer.suggestions.log.executions')" id="exec-table" />
        </x-card>
        <x-card :title="$t('approvals')">
            <x-datatable :table="$approvals" :url="route('marketer.suggestions.log.approvals')" id="approval-table" />
        </x-card>
    </div>
</x-layouts.marketer>
