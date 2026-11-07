<x-layouts.admin guard="central" :title="__('ai.menu.usage')">
    <div class="mb-5 grid gap-4 sm:grid-cols-4">
        <x-stat-card :label="__('ai.calls')" :value="number_format($totals['calls'])" />
        <x-stat-card :label="__('ai.tokens')" :value="number_format($totals['tokens'])" />
        <x-stat-card :label="__('ai.images')" :value="number_format($totals['images'])" />
        <x-stat-card :label="__('ai.cost')" :value="number_format($totals['cost'], 4)" />
    </div>
    <x-card>
        <x-datatable :table="$table" :url="route('central.ai.usage.datatable')" id="ai-usage-table" />
    </x-card>
</x-layouts.admin>
