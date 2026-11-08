@php $t = fn ($k, $r = []) => __('marketing.meta.'.$k, $r); $cols = ['spend', 'impressions', 'reach', 'clicks', 'ctr', 'cpc', 'cpm', 'conversions', 'conversion_value', 'roas']; @endphp
<x-layouts.marketer :title="$t('insights_title')">
    <div class="space-y-4" data-meta-insights>
        <a href="{{ route('marketer.meta-ads.index') }}" class="text-sm text-brand-600 hover:underline">← {{ $t('btn.back') }}</a>
        <h1 class="text-xl font-bold text-slate-800">{{ $t('insights_title') }}</h1>
        @unless ($status['configured'])<p class="rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700" role="status" data-not-configured>{{ $t('not_configured') }}</p>@endunless
        @if ($error)<p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert" data-meta-error>{{ $error }}</p>@endif
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div><label class="form-label" for="f-level">{{ $t('insights.level') }}</label><select id="f-level" name="level" class="form-control">@foreach (['campaign', 'adset', 'ad'] as $l)<option value="{{ $l }}" @selected($level === $l)>{{ $t('levels.'.$l) }}</option>@endforeach</select></div>
            <div><label class="form-label" for="f-preset">{{ $t('insights.preset') }}</label><select id="f-preset" name="preset" class="form-control">@foreach (config('meta.insights_presets') as $p)<option value="{{ $p }}" @selected($preset === $p)>{{ $t('presets.'.$p) }}</option>@endforeach</select></div>
            <x-button type="submit">{{ $t('btn.filter') }}</x-button>
        </form>
        <x-card>
            @if ($rows)
                <div class="overflow-x-auto" data-insights-table>
                    <table class="w-full text-sm">
                        <thead class="text-xs text-slate-500"><tr><th class="py-1 text-start">{{ $t('insights.name') }}</th>@foreach ($cols as $c)<th class="px-2 py-1 text-start">{{ $t('insights.'.$c) }}</th>@endforeach</tr></thead>
                        <tbody>@foreach ($rows as $r)<tr class="border-t border-slate-100"><td class="py-1.5">{{ $r['name'] ?? '—' }}</td>@foreach ($cols as $c)<td class="whitespace-nowrap px-2 py-1.5" dir="ltr">{{ $r[$c] ?? '—' }}</td>@endforeach</tr>@endforeach</tbody>
                    </table>
                </div>
            @else
                <p class="py-6 text-center text-sm text-slate-500">{{ $t('none') }}</p>
            @endif
        </x-card>
    </div>
</x-layouts.marketer>
