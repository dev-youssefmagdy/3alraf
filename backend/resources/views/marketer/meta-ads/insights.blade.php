@php $t = fn ($k, $r = []) => __('marketing.meta.'.$k, $r); $cols = ['spend', 'impressions', 'reach', 'clicks', 'ctr', 'cpc', 'cpm', 'conversions', 'conversion_value', 'roas']; @endphp
<x-layouts.marketer :title="$t('insights_title')">
    <x-slot:header><x-ui.page-header :title="$t('insights_title')" :breadcrumbs="[[$t('title'), route('marketer.meta-ads.index')]]" /></x-slot:header>
    <div class="space-y-4" data-meta-insights>
        @unless ($status['configured'])<x-ui.alert tone="info" data-not-configured>{{ $t('not_configured') }}</x-ui.alert>@endunless
        @if ($error)<x-ui.alert tone="danger" data-meta-error>{{ $error }}</x-ui.alert>@endif
        <form method="GET" class="grid grid-cols-1 items-end gap-3 sm:flex sm:flex-wrap">
            <x-ui.select name="level" :label="$t('insights.level')" :value="$level" :options="collect(['campaign', 'adset', 'ad'])->mapWithKeys(fn ($l) => [$l => $t('levels.'.$l)])->all()" />
            <x-ui.select name="preset" :label="$t('insights.preset')" :value="$preset" :options="collect(config('meta.insights_presets'))->mapWithKeys(fn ($p) => [$p => $t('presets.'.$p)])->all()" />
            <x-ui.button type="submit">{{ $t('btn.filter') }}</x-ui.button>
        </form>
        <x-ui.card>
            @if ($rows)
                <x-ui.table data-insights-table :mobile="false">
                    <thead><tr><th>{{ $t('insights.name') }}</th>@foreach ($cols as $c)<th>{{ $t('insights.'.$c) }}</th>@endforeach</tr></thead>
                    <tbody>@foreach ($rows as $r)<tr><td>{{ $r['name'] ?? '—' }}</td>@foreach ($cols as $c)<td class="whitespace-nowrap" dir="ltr">{{ $r[$c] ?? '—' }}</td>@endforeach</tr>@endforeach</tbody>
                </x-ui.table>
            @else
                <x-ui.empty-state kind="nodata" :title="$t('none')" message="" class="!py-6" />
            @endif
        </x-ui.card>
    </div>
</x-layouts.marketer>
